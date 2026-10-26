#!/usr/bin/env python3
"""
Marquee - "Your Name in Lights" for FPP   (version 3.1)
---------------------------------------------------------
Runs on your FPP master. Kids send their first name on your Marquee website.

  * While the show plays, this checks your website every 20 seconds.
  * When names are waiting, it tells FPP to play your short "Names in Lights"
    break right AFTER the current song finishes. Names never cover a song.
  * During the break it shows up to 6 names, one at a time, on your screens
    (any FPP Pixel Overlay Model: a matrix, a projector, a TV).
  * If your website has "Say each name out loud" on, it downloads each
    spoken name (made by your website with ElevenLabs) and plays it as the
    name goes up, optionally with a "talking faces" effect on your props.
  * It tells your website what FPP is playing (Show Control in WP Admin) and
    how its setup check went (Marquee -> Settings -> Names & FPP).

Your website never connects into your house: this script reaches out to it,
so no router ports need to be opened.

Easiest: install the Marquee plugin in FPP's Plugin Manager - it runs this script for you.
By hand: settings live in marquee_names.json next to this script. Download that file
from your website (Marquee -> Settings -> Names & FPP), or make it here with:
    python3 /home/fpp/media/scripts/marquee_names.py --setup

Install (once) - the full guide is "Marquee names - setup guide.txt":
  1. FPP -> Content Setup -> File Manager -> Scripts -> upload this file
     and marquee_names.json.
  2. FPP -> Content Setup -> Command Presets: FPPD_STARTED -> "Run Script"
     -> marquee_names.py
  3. Restart FPPD. The setup check shows on your website within a minute.
     Log: File Manager -> Logs -> marquee_names.log
"""
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import socket
import threading
import urllib.request

VERSION = "3.14.1"
HERE = os.path.dirname(os.path.abspath(__file__))
# Where the settings file may be: next to this script, or wherever FPP's File Manager
# put it (it files .json uploads under Uploads or Config, not Scripts).
CONFIG_PLACES = [os.path.join(HERE, "marquee_names.json"),
                 "/home/fpp/media/scripts/marquee_names.json",
                 "/home/fpp/media/upload/marquee_names.json",
                 "/home/fpp/media/uploads/marquee_names.json",
                 "/home/fpp/media/config/marquee_names.json"]
CONFIG = os.environ.get("MARQUEE_NAMES_CONFIG", "")


def config_path():
    if CONFIG:
        return CONFIG
    for p in CONFIG_PLACES:
        if os.path.exists(p):
            return p
    return CONFIG_PLACES[0]
MASTER = os.environ.get("MARQUEE_FPP", "http://127.0.0.1")
LOCK = os.environ.get("MARQUEE_LOCK", "/tmp/marquee_names.pid")
PLUGIN = os.environ.get("MARQUEE_PLUGIN", "") == "1"          # started by the Marquee FPP plugin
LOGFILE = os.environ.get("MARQUEE_LOG", "/home/fpp/media/logs/marquee_names.log")
DATA = os.environ.get("MARQUEE_DATA", HERE)                   # break state (and the plugin's restart request)
MUSIC = os.environ.get("MARQUEE_MUSIC", "/home/fpp/media/music")   # spoken names are saved here (FPP plays media from this folder)
VOICE_PREFIX = "marquee-voice-"
VOICE_KEEP = 300                                                    # newest clips kept on the FPP
UA = "Mozilla/5.0 (X11; Linux aarch64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 FPP-Marquee/" + VERSION

DEFAULTS = {
    "site": "",                       # your website, e.g. https://yourshow.com/
    "key": "",                        # private key from Marquee -> Settings -> Names & FPP
    "break_playlist": "Names in Lights",
    "screens": [],                    # [{"name": "matrix", "fpp": "http://192.168.1.50", "model": "MatrixText"}]
    "names_start": 4.0,               # seconds into the break when the first name appears
    "slot": 3.75,                     # seconds per name
    "show_for": 3,                    # seconds each name stays up
    "max_names": 6,                   # names per break; the rest wait for the next one
    "max_width": 0.62,                # text stays inside this part of the screen (fraction of width)
    "max_height": 0.42,               # ... and of height
    "font": "",                       # "" picks a bold font automatically
    "scroll": "auto",                 # old setting (each screen has its own Text style now): "auto", "always" or "never"
    "check_every": 20,                # seconds between website checks during songs
    "voice": True,                    # play spoken names when the website sends them (false = never)
    "voice_volume": 0,                # -100..100, added to FPP's volume for the spoken names
    "voice_slot": 2,                  # FPP media stream slot for the names (slot 1 is the break's own music)
    "short_breaks": True,             # 1 or 2 names: use "<break playlist> 1" / "<break playlist> 2" if they exist
    "say_every_time": True,           # with fewer names than slots the screens repeat them: say them again each time
    "talk_effect": "",                # FPP effect (.eseq) that moves the singing faces while a name is spoken
    "mode": "break",                  # "break": a names break between songs; "live": during songs on a spare screen
    "live_show_for": 6,               # live mode: seconds each name stays up
    "only_playlists": [],             # names go up only while one of these playlists plays ([] = any playlist)
    "simple_break": False,            # no names break playlist on this FPP: make a simple one (no sequence needed)
    "break_own": False,               # the show's own names break audio: don't put the website's in the playlists, use the website's timing
    "per_break": 6,                   # names in one break: fewer = each is up longer (spread over the same part of the break)
    "every": 1,                       # a names break after every this many songs (names collect in between)
}
MODE = {"mode": None, "show_for": None}   # what the website said last (it wins over the settings file)
ADVISORY = {"now": None, "last": 0.0}     # traffic & parking alert from Show Control, put on the screens now and then


def live():
    return (MODE["mode"] or S["mode"]) == "live"

S = dict(DEFAULTS)


LOGBUF = []          # the last log lines, for a Marquee Support session


def log(*a):
    line = time.strftime("%Y-%m-%d %H:%M:%S ") + " ".join(str(x) for x in a)
    print(line, flush=True)
    LOGBUF.append(line)
    del LOGBUF[:-80]
    try:
        if not PLUGIN and os.path.exists(LOGFILE) and os.path.getsize(LOGFILE) > 500000:   # FPP rotates the plugin's log itself
            os.replace(LOGFILE, LOGFILE + ".old")
        with open(LOGFILE, "a") as f:
            f.write(line + "\n")
    except OSError:
        pass


# ------------------------------------------------------------ settings ----
def load_settings():
    """Read marquee_names.json. Returns a list of problems (empty = good)."""
    S.clear()
    S.update(DEFAULTS)
    cfg = config_path()
    if not os.path.exists(cfg):
        return ["no settings file. Download marquee_names.json from your website "
                "(Marquee -> Settings -> Names & FPP) and upload it in FPP -> File Manager "
                "(looked in: %s)" % ", ".join(sorted(set(os.path.dirname(p) for p in CONFIG_PLACES)))]
    try:
        with open(cfg) as f:
            data = json.load(f)
    except (OSError, ValueError) as e:
        return ["could not read %s: %s" % (cfg, e)]
    if isinstance(data, dict):
        S.update({k: v for k, v in data.items() if k in DEFAULTS})
    problems = []
    if not str(S["site"]).startswith(("http://", "https://")):
        problems.append("the website address in the settings file must start with https://")
    if len(str(S["key"])) < 20:
        problems.append("the private key in the settings file is missing or too short")
    S["screens"] = clean_screens(S["screens"])
    break_timing()
    return problems


def load_settings_keep():
    """Settings-file problems for a re-check, without losing what the website sent since start-up."""
    keep = {k: S[k] for k in ("screens", "only_playlists", "simple_break", "break_own") + SITE_KEYS}
    problems = load_settings()
    S.update(keep)
    break_timing()
    return problems


def clean_screens(rows):
    screens = []
    for i, sc in enumerate(rows or []):
        if isinstance(sc, dict) and sc.get("fpp") and sc.get("model"):
            host = str(sc["fpp"]).strip()
            if not host.startswith("http"):
                host = "http://" + host
            pick = lambda k, ok, d: str(sc.get(k) or d) if str(sc.get(k) or d) in ok else d
            screens.append({"name": (str(sc.get("name") or "") or "screen %d" % (i + 1)).strip(), "fpp": host.rstrip("/"), "model": str(sc["model"]).strip(),
                            "style": pick("style", ("auto", "still", "scroll"), "auto"),
                            "size": pick("size", ("auto", "s", "m", "l", "xl", "custom"), "auto"),
                            "px": max(0, min(400, int(float(sc.get("px") or 0)))),
                            "speed": pick("speed", ("slow", "normal", "fast"), "normal")})
    return screens


def break_timing():
    if S.get("break_own"):
        return                                   # the show's own audio: the timing set on the website is the one
    st = break_state()
    if st.get("start") and st.get("slot"):     # timing of the break audio built on the website wins
        S["names_start"] = float(st["start"])
        S["slot"] = float(st["slot"])
        S["show_for"] = max(2.0, float(st["slot"]) - 0.6)


SITE_KEYS = ("break_playlist", "names_start", "slot", "show_for", "talk_effect", "scroll")


def site_config(cfg):
    """Your screens and timing as set on the website now (so changing them there needs no new settings file)."""
    if not isinstance(cfg, dict):
        return
    if "screens" in cfg:
        new = clean_screens(cfg.get("screens"))
        if new != LAST.get("screens"):
            log("screens from the website:", ", ".join("%s=%s on %s" % (x["name"], x["model"], x["fpp"]) for x in new) or "none")
            if "screens" in LAST:
                CHECK["want"] = CHECK["want"] or "changed"       # run the setup check again with the new screens
            LAST["screens"] = new
            _info.clear()
        S["screens"] = new
    if "break_own" in cfg:
        S["break_own"] = bool(cfg.get("break_own"))
    if cfg.get("every"):
        try:
            S["every"] = max(1, min(6, int(cfg["every"])))
        except (TypeError, ValueError):
            pass
    if cfg.get("per_break"):
        try:
            S["per_break"] = max(1, min(6, int(cfg["per_break"])))
        except (TypeError, ValueError):
            pass
    if "simple_break" in cfg:
        S["simple_break"] = bool(cfg.get("simple_break"))
        LAST["site_simple"] = True                  # the website (Marquee 0.9+) has the "simple names break" switch
    if isinstance(cfg.get("only_playlists"), list):
        new = [str(p).strip() for p in cfg["only_playlists"] if str(p).strip()][:20]
        if new != S["only_playlists"]:
            log("names only during:", ", ".join(new) if new else "any playlist")
            if "only" in LAST:
                CHECK["want"] = CHECK["want"] or "changed"
            S["only_playlists"] = new
        LAST["only"] = True
    if cfg.get("break_playlist") not in (None, "") and str(cfg["break_playlist"]) != S["break_playlist"] and "bp" in LAST:
        CHECK["want"] = CHECK["want"] or "changed"
    LAST["bp"] = True
    for k in SITE_KEYS:
        if cfg.get(k) not in (None, ""):
            try:
                S[k] = str(cfg[k]) if isinstance(DEFAULTS[k], str) else float(cfg[k])
            except (TypeError, ValueError):
                pass
    break_timing()


# ---------------------------------------------------------------- HTTP ----
class _KeepPost(urllib.request.HTTPRedirectHandler):
    """Your website moved the request (http -> https, or with/without www): send it on as the same POST.
    Python would turn it into a GET and drop what the FPP sent (shown names, the setup check, its version),
    so the website only ever saw a ping."""
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if req.data is not None and code in (301, 302, 303, 307, 308):
            old, new = urllib.parse.urlparse(req.full_url), urllib.parse.urlparse(newurl)
            same = lambda h: (h or "").lower()[4:] if (h or "").lower().startswith("www.") else (h or "").lower()
            if old.hostname and same(old.hostname) == same(new.hostname):        # the same website, only moved
                MOVED["to"] = "%s://%s/" % (new.scheme, new.netloc)
                return urllib.request.Request(newurl, data=req.data, headers=dict(req.header_items()), method="POST")
        return super().redirect_request(req, fp, code, msg, headers, newurl)


MOVED = {"to": "", "said": False}
_OPENER = urllib.request.build_opener(_KeepPost)


def http_json(url, data=None, timeout=15):
    if data is not None:
        data = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(url, data=data, headers={"User-Agent": UA, "Accept": "application/json, text/plain, */*"})
    with _OPENER.open(req, timeout=timeout) as r:
        body = r.read().decode("utf-8", "replace")
    if MOVED["to"] and str(S.get("site", "")) and not str(S["site"]).startswith(MOVED["to"]):
        if not MOVED["said"]:
            log("your website moved this FPP's requests to %s - using that address from now on "
                "(download marquee_names.json again to save it)" % MOVED["to"])
            MOVED["said"] = True
        S["site"] = MOVED["to"]
    try:
        return json.loads(body or "null")
    except ValueError:
        if "captcha" in body.lower():
            raise RuntimeError("your web host's bot protection blocked the request (see the setup guide, 'Bot protection')")
        raise RuntimeError("expected JSON but got: " + body[:120].replace("\n", " "))


def fpp_get(host, path, timeout=5):
    return http_json(host.rstrip("/") + path, timeout=timeout)


def fpp_command(host, name, args, timeout=5):
    body = json.dumps({"command": name, "args": [str(a) for a in args]}).encode()
    req = urllib.request.Request(host.rstrip("/") + "/api/command", data=body,
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read().decode("utf-8", "replace")


# ------------------------------------------------------------- website ----
def pull(done_ids, st=None, report=None):
    """Ask the website for waiting names; report the ones already shown, what FPP is doing, and the setup check."""
    t_start = time.time()
    url = str(S["site"]).rstrip("/") + "/wp-admin/admin-ajax.php?action=rr_names_pull&key=" + urllib.parse.quote(str(S["key"]))
    data = {"done": ",".join(str(i) for i in done_ids)} if done_ids else {"ping": "1"}
    if st is not None:
        info = {"status": status_name(st), "playlist": playlist_name(st), "sequence": st.get("current_sequence", ""),
                # 3.13+: where the show is in "names break after every N songs", for the website's messages and buzz
                "played": SONGS["played"], "every": int(S.get("every") or 1), "queued": bool(QUEUED["on"])}
        if time.time() - PL_SENT["t"] > 600:          # the playlist list, for the website's "Only during these playlists"
            pls = fpp_playlists()
            if pls:
                info["playlists"] = [str(p) for p in pls][:60]
                PL_SENT["t"] = time.time()
                # 3.10+: what the website needs to make setup easy (screens to pick from, the show playlist to suggest)
                info["script"] = VERSION
                info["fpp_version"] = str(st.get("version") or "")[:20]
                models = fpp_models()
                if models is not None:
                    info["models"] = models
                info["scheduled"] = fpp_scheduled()
        if TESTS["results"]:
            info["tested"] = TESTS["results"][-5:]
            TESTS["results"] = []
        data["fpp"] = json.dumps(info)
    if report is not None:
        data["report"] = json.dumps(report)
    res = http_json(url, data)
    if isinstance(res, dict) and isinstance(res.get("data"), dict):
        d = res["data"]
        if d.get("check") and str(d["check"]) != CHECK["done"]:      # "Check again" on the website
            CHECK["want"] = str(d["check"])
        t = d.get("test")
        if isinstance(t, dict) and t.get("id") and str(t["id"]) not in TESTS["done"]:
            TESTS["want"] = t
        if d.get("fast"):                                            # someone is setting up: check in more often for a bit
            FAST["until"] = time.time() + 180
    if not res or not res.get("success"):
        raise RuntimeError("website said: %s" % (res,))
    if isinstance(res["data"], dict):
        if res["data"].get("mode") in ("break", "live"):
            if MODE["mode"] and MODE["mode"] != res["data"]["mode"]:
                log("website switched names to", "during songs on the screens" if res["data"]["mode"] == "live" else "a names break between songs")
            MODE["mode"] = res["data"]["mode"]
        ADVISORY["now"] = res["data"].get("advisory") if isinstance(res["data"].get("advisory"), dict) else None
        if res["data"].get("live_show_for"):
            MODE["show_for"] = float(res["data"]["live_show_for"])
        site_config(res["data"].get("config"))
        if isinstance(res["data"].get("break"), dict):
            LAST["break"] = res["data"]["break"]
        sup = res["data"].get("support")
        SUPPORT["on"] = bool(isinstance(sup, dict) and sup.get("on"))
        if SUPPORT["on"]:
            try:
                support_answer(sup)
            except Exception as e:
                log("support view: could not send -", e)
        new = {t["slug"]: t for t in (res["data"].get("themes") or []) if isinstance(t, dict) and t.get("slug") and t.get("playlist")}
        if new != THEMES:
            THEMES.clear()
            THEMES.update(new)
        if t_start >= LATEST["t"]:
            LATEST.update(data=res["data"], t=t_start)
    if isinstance(res["data"], dict) and res["data"].get("break"):
        try:
            sync_break(res["data"]["break"])
        except Exception as e:
            log("could not set up the names break from the website:", e)
    return res["data"]


# --------------------------------------------------- break from website ----
STATE = os.path.join(DATA, "marquee_break_state.json")
BREAK_FILES = {"full": "marquee-break-full.wav", "short2": "marquee-break-2.wav", "short1": "marquee-break-1.wav"}


def break_state():
    try:
        with open(STATE) as f:
            d = json.load(f)
        return d if isinstance(d, dict) else {}
    except (OSError, ValueError):
        return {}


def download(url, path, limit=40_000_000):
    req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "audio/*,*/*"})
    with urllib.request.urlopen(req, timeout=60) as r:
        data = r.read(limit)
    if len(data) < 1000 or data[:4] not in (b"RIFF", b"ID3\x03", b"ID3\x04") and data[:1] != b"\xff":
        raise RuntimeError("the website sent back a page instead of audio (bot protection?)")
    tmp = path + ".part"
    with open(tmp, "wb") as f:
        f.write(data)
    os.replace(tmp, path)


def sync_break(b):
    """The website built new break audio: download it and set up the break playlists (full, 1 and 2 names)."""
    if not isinstance(b, dict) or not b.get("v") or not b.get("full") or S.get("break_own"):
        return
    st = break_state()
    if str(st.get("v")) == str(b["v"]) or in_break(master_status()):
        return
    bp = S["break_playlist"]
    base = fpp_playlist(bp)
    simple = is_simple(base, st) or (base is None and S["simple_break"] and not live())
    if base is None and not simple:
        raise RuntimeError("there's no playlist named '%s' on this FPP - add one with your names sequence in it" % bp)
    for k, name in BREAK_FILES.items():
        if not b.get(k):
            return
        download(b[k], os.path.join(MUSIC, name))
    if simple:                                  # Marquee's own names break: it just plays the new audio
        st.update({"v": str(b["v"]), "start": float(b.get("start") or S["names_start"]), "slot": float(b.get("slot") or S["slot"]),
                   "t": time.strftime("%Y-%m-%d %H:%M"), "simple": True})
        st.pop("simple_sig", None)
        save_state(st)
        S["names_start"], S["slot"] = st["start"], st["slot"]
        S["show_for"] = max(2.0, st["slot"] - 0.6)
        ensure_simple_break()
        return
    entries = [e for e in (base.get("mainPlaylist") or []) if isinstance(e, dict) and e.get("sequenceName")]
    if not entries:
        raise RuntimeError("playlist '%s' has no sequence in it - add your names sequence to it first" % bp)
    if "backup" not in st:
        st["backup"] = base                    # the playlist as it was before Marquee changed it
    entry = dict(entries[0])
    for n, (key, pl) in enumerate((("full", bp), ("short2", bp + " 2"), ("short1", bp + " 1"))):
        e = dict(entry)
        e["type"] = "both"
        e["mediaName"] = BREAK_FILES[key]
        new = dict(base)
        new["name"] = pl
        new["mainPlaylist"] = [e]
        new["leadIn"] = []
        new["leadOut"] = []
        save_playlist(pl, new)
    st.update({"v": str(b["v"]), "start": float(b.get("start") or S["names_start"]), "slot": float(b.get("slot") or S["slot"]),
               "seq": entry.get("sequenceName"), "t": time.strftime("%Y-%m-%d %H:%M")})
    save_state(st)
    S["names_start"], S["slot"] = st["start"], st["slot"]
    S["show_for"] = max(2.0, st["slot"] - 0.6)
    _playlists["t"] = 0
    log("names break updated from the website: '%s', '%s 2' and '%s 1' now play the new audio "
        "(names start at %ss, one every %ss)" % (bp, bp, bp, st["start"], st["slot"]))


def save_state(st):
    with open(STATE, "w") as f:
        json.dump(st, f, indent=1)


def fpp_playlist(name):
    """One playlist from this FPP, or None when there's no playlist by that name."""
    try:
        pl = fpp_get(MASTER, "/api/playlist/" + urllib.parse.quote(name))
    except urllib.error.HTTPError as e:
        if e.code == 404:
            return None
        raise
    if not isinstance(pl, dict) or (str(pl.get("Status", "")).lower() == "error" and not pl.get("mainPlaylist")):
        return None
    return pl


def save_playlist(name, data):
    req = urllib.request.Request(MASTER.rstrip("/") + "/api/playlist/" + urllib.parse.quote(name), data=json.dumps(data).encode(),
                                 headers={"Content-Type": "application/json"})
    urllib.request.urlopen(req, timeout=10).read()


# --------------------------------------------- simple break (no sequence) ----
SIMPLE_DESC = "Made by Marquee: names break with no sequence"


def is_simple(pl, st=None):
    """A names break playlist Marquee made itself (and nobody has put a sequence in since)."""
    if not isinstance(pl, dict):
        return False
    if str(pl.get("desc") or "") != SIMPLE_DESC:
        return False
    return not any(isinstance(e, dict) and e.get("sequenceName") for e in (pl.get("mainPlaylist") or []))


def simple_sig(st):
    return "%s|%s|%s|%s" % ((st.get("v") or "") if st.get("simple") else "", float(S["names_start"]), float(S["slot"]), int(S["max_names"]))


def ensure_simple_break():
    """No names break on this FPP? Make a simple one: the lights pause, the names go up on the screens,
    and the names break audio plays if it's been built on the website. A names break playlist the show
    already has is never touched. Returns a line for the log ('' = nothing to do)."""
    if live() or not S["simple_break"]:
        return ""
    bp = S["break_playlist"]
    base = fpp_playlist(bp)
    st = break_state()
    if base is not None and not is_simple(base):
        if st.get("simple"):                        # someone put a sequence in it: it's theirs now
            st.pop("simple", None)
            st.pop("simple_sig", None)
            save_state(st)
        return ""
    _playlists["t"] = 0
    have = fpp_playlists()
    sig = simple_sig(st)
    if base is not None and st.get("simple_sig") == sig and all("%s %d" % (bp, n) in have for n in (1, 2)):
        return ""
    if in_break(master_status()):
        return ""
    audio = bool(st.get("simple") and st.get("v"))
    for key, pl, slots in (("full", bp, int(S["max_names"])), ("short2", bp + " 2", 4), ("short1", bp + " 1", 2)):
        if pl != bp:
            other = fpp_playlist(pl)
            if other is not None and not is_simple(other):
                continue                            # a short break of the show's own: leave it
        if audio and os.path.exists(os.path.join(MUSIC, BREAK_FILES[key])):
            entry = {"type": "media", "enabled": 1, "playOnce": 0, "mediaName": BREAK_FILES[key]}
        else:
            entry = {"type": "pause", "enabled": 1, "playOnce": 0,
                     "duration": round(float(S["names_start"]) + slots * float(S["slot"]) + 1.5, 1)}
        save_playlist(pl, {"name": pl, "desc": SIMPLE_DESC, "mainPlaylist": [entry], "leadIn": [], "leadOut": [],
                           "repeat": 0, "loopCount": 0})
    st.update({"simple": True, "simple_sig": sig})
    save_state(st)
    _playlists["t"] = 0
    msg = "made a simple names break: '%s' (and '%s 1', '%s 2' for one or two names) - %s, then the names go up on your screens" % (
        bp, bp, bp, "the names break audio plays" if audio else "the show pauses")
    log(msg)
    return msg


# ------------------------------------------------------------- support ----
SUPPORT = {"on": False, "id": "", "sent": 0, "tests": set(), "done": set()}
LAST = {"break": None}
CHECK = {"want": "", "done": ""}           # a setup check the website asked for, or one owed after a settings change
TESTS = {"want": None, "done": set(), "results": []}   # "Send a test name" from the website
FAST = {"until": 0.0}
LATEST = {"data": None, "t": 0.0}          # the newest answer from the website (when that check-in started)
BG = {"thread": None, "result": None, "error": None, "done": [], "started": 0.0}


def support_view():
    """What Marquee Support sees during a session: read-only facts about this FPP, no secrets."""
    st = master_status()
    cp = st.get("current_playlist") or {}
    view = {"v": VERSION, "t": int(time.time()),
            "status": status_name(st), "playlist": cp.get("playlist", "") if isinstance(cp, dict) else "",
            "sequence": st.get("current_sequence", ""), "song": st.get("current_song", ""),
            "fpp_version": st.get("version", "") or "", "mode": MODE["mode"] or "",
            "break_playlist": S["break_playlist"], "names_start": S["names_start"], "slot": S["slot"],
            "screens": [], "playlists": [], "log": LOGBUF[-60:], "plugin": PLUGIN, "sequences": []}
    try:
        view["sequences"] = [str(x) for x in (fpp_get(MASTER, "/api/sequence") or [])][:80]
    except Exception:
        pass
    try:
        view["playlists"] = [str(p) for p in (fpp_get(MASTER, "/api/playlists") or [])][:60]
    except Exception as e:
        view["playlists_error"] = str(e)[:120]
    for sc in S["screens"]:
        row = {"name": sc["name"], "fpp": sc["fpp"], "model": sc["model"]}
        try:
            m = fpp_get(sc["fpp"], "/api/overlays/model/" + urllib.parse.quote(sc["model"])) or {}
            row["size"] = "%s x %s" % (m.get("width", "?"), m.get("height", "?"))
            row["ok"] = True
        except Exception as e:
            row["ok"] = False
            row["error"] = str(e)[:120]
        view["screens"].append(row)
    return view


def support_answer(sup):
    """The website asked for a fresh view and/or a test name (only while the owner has support turned on)."""
    if str(sup.get("id", "")) != SUPPORT["id"]:   # a new support session: start counting again
        SUPPORT.update({"id": str(sup.get("id", "")), "sent": 0, "tests": set(), "done": set()})
    shown = []
    for t in (sup.get("tests") or [])[:3]:
        tid = str(t.get("id", ""))
        if not tid or tid in SUPPORT["tests"]:
            continue
        SUPPORT["tests"].add(tid)
        text = str(t.get("text") or "TEST")[:40]
        try:
            n = show(text, "#FFFFFF")
        except Exception as e:
            log("support: test name could not be shown -", e)
            n = 0
        log("support: test name", repr(text), "shown on %d screen(s)" % n)
        shown.append({"id": tid, "screens": n})
    acted, restart = [], False
    for a in (sup.get("actions") or [])[:3]:
        aid, do = str(a.get("id", "")), str(a.get("do", ""))
        if not aid or aid in SUPPORT["done"]:
            continue
        SUPPORT["done"].add(aid)
        ok, msg = support_action(do)
        log("support:", msg)
        acted.append({"id": aid, "ok": ok, "msg": msg[:200]})
        restart = restart or (do == "restart" and ok)
    want = int(sup.get("want") or 0)
    if not shown and not acted and want <= SUPPORT["sent"]:
        return
    SUPPORT["sent"] = max(want, SUPPORT["sent"])
    view = support_view()
    view["want"] = want
    view["tests"] = shown
    view["actions"] = acted
    url = str(S["site"]).rstrip("/") + "/wp-admin/admin-ajax.php?action=marquee_support_fpp&key=" + urllib.parse.quote(str(S["key"]))
    http_json(url, {"view": json.dumps(view)})
    if restart:
        restart_self("restart asked for by Marquee Support")


def support_action(do):
    """The few fixes Marquee Support can ask for (only while the owner has a support session open)."""
    try:
        if do == "restart":
            return True, "names script restarting"
        if do == "check":
            checks, site_ok = run_checks(load_settings_keep())
            if site_ok:
                send_report(checks)
            bad = [c["msg"] for c in checks if not c["ok"]]
            return True, "setup check ran: " + ("all good" if not bad else "; ".join(bad))
        if do == "playlists":
            if live():
                return False, "names go up during songs, so there are no names break playlists to set up"
            if not LAST["break"]:
                return False, "no names break audio has been built on the website yet (Settings -> Names & FPP -> Names break audio)"
            st = break_state()
            st.pop("v", None)
            with open(STATE, "w") as f:
                json.dump(st, f, indent=1)
            sync_break(LAST["break"])
            return True, "names break playlists set up again: '%s', '%s 2' and '%s 1'" % ((S["break_playlist"],) * 3)
        return False, "unknown fix '%s' (update the names script)" % do
    except Exception as e:
        return False, "%s failed: %s" % (do, e)


def self_stamp():
    """When this script file last changed (a plugin update or a new copy put on by hand)."""
    try:
        st = os.stat(os.path.abspath(__file__))
        return (st.st_mtime, st.st_size)
    except OSError:
        return None


def restart_self(why):
    log(why)
    try:
        os.remove(LOCK)
    except OSError:
        pass
    os.execv(sys.executable, [sys.executable, os.path.abspath(__file__)] + sys.argv[1:])


# -------------------------------------------------------------- master ----
def master_status():
    try:
        return fpp_get(MASTER, "/api/fppd/status") or {}
    except Exception as e:
        log("could not read FPP status:", e)
        return {}


def status_name(st):
    """FPP's status_name, fixed for FPP 10.1.x: it can say "playing media" while a playlist is playing
    (status 1 with a current playlist). That's a playlist, so call it "playing"."""
    sn = str(st.get("status_name") or "")
    if sn.startswith("playing") and str(st.get("status")) == "1" and playlist_name(st):
        return "playing"
    return sn


def playlist_name(st):
    cp = st.get("current_playlist") or {}
    return cp.get("playlist", "") if isinstance(cp, dict) else ""


def in_break(st):
    if status_name(st) != "playing":
        return False
    pl = playlist_name(st)
    if pl == S["break_playlist"] or pl in short_playlists().values() or pl in theme_playlists().values():
        return True
    seq = (st.get("current_sequence") or "").lower().replace("_", " ")
    return "names in lights" in seq


def elapsed(st):
    try:
        if "milliseconds_elapsed" in st:
            return float(st["milliseconds_elapsed"]) / 1000.0
        return float(st.get("seconds_elapsed", 0))
    except (TypeError, ValueError):
        return 0.0


_playlists = {"t": 0, "names": []}
PL_SENT = {"t": 0}


def names_allowed(st):
    """Names go up only while one of the chosen playlists plays (none chosen = any playlist)."""
    want = [p.lower() for p in (S.get("only_playlists") or [])]
    return not want or playlist_name(st).lower() in want


def fpp_playlists():
    """Playlist names on this FPP (cached for a minute)."""
    if time.time() - _playlists["t"] > 60:
        try:
            _playlists["names"] = fpp_get(MASTER, "/api/playlists") or []
            _playlists["t"] = time.time()
        except Exception:
            pass
    return _playlists["names"]


def fpp_models():
    """This FPP's Pixel Overlay Models with their size, for the website's screen picker:
    [{"name", "w", "h", "screen"}] - "screen" marks the ones that look like a matrix, panels, TV or projector.
    None when FPP can't be asked (an older FPP, or it's busy)."""
    try:
        raw = fpp_get(MASTER, "/api/overlays/models", timeout=10) or []
    except Exception:
        return None
    rows, seen = [], set()
    for m in raw if isinstance(raw, list) else []:
        n = (m.get("Name") or m.get("name")) if isinstance(m, dict) else m
        if not n or str(n) in seen:
            continue
        seen.add(str(n))
        row = {"name": str(n)[:60]}
        if isinstance(m, dict) and m.get("width"):
            row["w"], row["h"] = int(m.get("width") or 0), int(m.get("height") or 0)
            orient = str(m.get("Orientation") or "").lower()
        else:
            orient = ""
            if len(rows) < 40:                      # an older FPP lists names only: ask for each size
                try:
                    d = fpp_get(MASTER, "/api/overlays/model/" + urllib.parse.quote(str(n))) or {}
                    row["w"], row["h"] = int(d.get("width") or 0), int(d.get("height") or 0)
                except Exception:
                    pass
        w, h = row.get("w", 0), row.get("h", 0)
        named = any(k in str(n).lower() for k in ("matrix", "panel", "screen", "tv", "proj", "hdmi", "video", "sign", "marquee"))
        row["screen"] = bool(str(n).upper().startswith("FB") or named or (orient in ("horizontal", "vertical") and w >= 20 and h >= 8))
        rows.append(row)
    rows.sort(key=lambda r: (not r["screen"], r["name"].lower()))
    return rows[:150]


def fpp_scheduled():
    """Playlists FPP's scheduler runs, with how many items each has (the show is the long one,
    a static or background playlist is short): [{"name", "items"}]."""
    try:
        sched = fpp_get(MASTER, "/api/schedule") or []
    except Exception:
        return []
    found = []

    def walk(x):
        if isinstance(x, dict):
            if str(x.get("enabled", 1)) not in ("0", "false", "False") and isinstance(x.get("playlist"), str) and x["playlist"].strip():
                if x["playlist"] not in found:
                    found.append(x["playlist"])
            for v in x.values():
                if isinstance(v, (dict, list)):
                    walk(v)
        elif isinstance(x, list):
            for v in x:
                walk(v)
    walk(sched)
    out = []
    for name in found[:12]:
        row = {"name": name[:80], "items": 0}
        try:
            pl = fpp_get(MASTER, "/api/playlist/" + urllib.parse.quote(name)) or {}
            row["items"] = len(pl.get("mainPlaylist") or [])
        except Exception:
            pass
        out.append(row)
    return out


def short_playlists():
    """The short breaks that exist: {name count: playlist}, e.g. {1: "Names in Lights 1", 2: "Names in Lights 2"}."""
    if not S["short_breaks"]:
        return {}
    have = fpp_playlists()
    out = {}
    for n in (1, 2):
        name = "%s %d" % (S["break_playlist"], n)
        if name in have:
            out[n] = name
    return out


def slots_for(st):
    """Name slots in the break that is playing: a short break says each of its 1 or 2 names twice."""
    for n, name in short_playlists().items():
        if playlist_name(st) == name:
            return n * 2
    return int(S["max_names"])


# ------------------------------------------------------ themed sequences ----
THEMES = {}      # slug -> {"name", "playlist", "start", "slot"} from the website
SKIPS = {}       # name id -> breaks it already waited through (it rides along in the next one)


def theme_playlists():
    """Themes whose playlist exists on this FPP: {slug: playlist}. A theme pointed at the names break
    playlist itself isn't a separate show: it plays the break (and its audio), so it's left out here
    and that break's own timing is used, not the theme row's."""
    have = fpp_playlists()
    own = {S["break_playlist"], S["break_playlist"] + " 1", S["break_playlist"] + " 2"}
    return {k: t["playlist"] for k, t in THEMES.items() if t["playlist"] in have and t["playlist"] not in own}


def name_theme(item):
    """The theme a name goes up in ('' = the classic break). A theme without a playlist on this FPP falls back to classic."""
    t = item.get("theme") or ""
    return t if t in theme_playlists() else ""


def eligible(names, theme):
    """Names that go up in a break of this theme: the ones that picked it, plus any that already waited a break."""
    return [n for n in names if name_theme(n) == theme or SKIPS.get(n.get("id"), 0) >= 1]


def plan_break(names):
    """Which playlist to play next: the theme the longest-waiting kid picked."""
    theme = name_theme(names[0]) if names else ""
    if theme:
        return theme_playlists()[theme]
    return short_playlists().get(len(eligible(names, "")), S["break_playlist"])


def current_theme(st):
    pl = playlist_name(st)
    for k, name in theme_playlists().items():
        if name == pl:
            return k
    return ""


SONGS = {"key": None, "played": 0}       # songs finished since the last names break
QUEUED = {"on": False}                   # a names break is lined up after the current song


def count_songs(st):
    """Count the songs that finish while the show plays (not the names break itself)."""
    if status_name(st) != "playing" or in_break(st):
        return
    cp = st.get("current_playlist") or {}
    key = "%s|%s|%s" % (playlist_name(st), cp.get("index", "") if isinstance(cp, dict) else "", st.get("current_sequence") or st.get("current_song") or "")
    if SONGS["key"] is not None and key != SONGS["key"]:
        SONGS["played"] += 1
    SONGS["key"] = key


def break_due(waiting):
    """Time for a names break after the current song? Every N songs, or sooner when a full break's worth is waiting."""
    every = int(S.get("every") or 1)
    return every <= 1 or SONGS["played"] >= every - 1 or waiting >= int(S["max_names"])


def queue_break(names=None):
    # "ifNotRunning" = true so a second request never stacks a second break
    pl = plan_break(names or [])
    fpp_command(MASTER, "Insert Playlist After Current", [pl, -1, -1, "true"])
    return pl


# --------------------------------------------------------------- voice ----
def voice_file(item):
    """Local file name for a name's spoken clip, downloading it if needed. None = no voice for this name."""
    url = item.get("audio") if S["voice"] else None
    if not url or not str(url).startswith(("http://", "https://")):
        return None
    base = os.path.basename(urllib.parse.urlparse(url).path)
    if not base.endswith((".mp3", ".wav")) or "/" in base or base.startswith("."):
        return None
    name = VOICE_PREFIX + base
    path = os.path.join(MUSIC, name)
    if os.path.exists(path) and os.path.getsize(path) > 500:
        return name
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "audio/mpeg,*/*"})
        with urllib.request.urlopen(req, timeout=10) as r:
            data = r.read(3_000_000)
        if len(data) < 500 or data[:1] == b"<":
            raise RuntimeError("the website sent back a page instead of audio")
        tmp = path + ".part"
        with open(tmp, "wb") as f:
            f.write(data)
        os.replace(tmp, path)
        tidy_voice()
        return name
    except Exception as e:
        log("could not download the spoken name for", item.get("text"), "-", e)
        return None


def tidy_voice():
    try:
        clips = sorted((os.path.getmtime(os.path.join(MUSIC, f)), f) for f in os.listdir(MUSIC) if f.startswith(VOICE_PREFIX))
        for _, f in clips[:-VOICE_KEEP]:
            os.remove(os.path.join(MUSIC, f))
    except OSError:
        pass


def prefetch(names):
    for item in names[:int(S["max_names"])]:
        voice_file(item)
        picture(item)


# ------------------------------------------------------------ pictures ----
PICS = {}        # name id -> the kid's picture (JPEG bytes), approved on the website (3.14+)
NOPIC = {}       # FPP address -> True when that FPP can't take pictures (older than FPP 10)


def picture(item):
    """The kid's picture for a name, downloaded once; None when there isn't one."""
    url = item.get("photo")
    if not url or not str(url).startswith(("http://", "https://")):
        return None
    k = item.get("id")
    if k in PICS:
        return PICS[k]
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "image/jpeg,image/*"})
        with urllib.request.urlopen(req, timeout=10) as r:
            data = r.read(400_000)
        if data[:2] != b"\xff\xd8":
            raise RuntimeError("the website sent back a page instead of a picture")
        PICS[k] = data
        if len(PICS) > 40:
            PICS.pop(next(iter(PICS)))
        return data
    except Exception as e:
        log("could not download the picture for", item.get("text"), "-", e)
        PICS[k] = None
        return None


def fpp_put_image(host, model, data, timeout=4):
    """Put a picture on an overlay model (FPP 10+ scales it to fit). Raises on an error."""
    q = urllib.parse.urlencode({"fmt": "image", "scale": "fit", "autoEnable": "Enabled", "stopEffect": "true"})
    req = urllib.request.Request(host.rstrip("/") + "/api/overlays/model/" + urllib.parse.quote(model) + "/data?" + q,
                                 data=data, method="PUT", headers={"Content-Type": "image/jpeg"})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read().decode("utf-8", "replace")


def fpp_put(host, path, body=None, timeout=3):
    req = urllib.request.Request(host.rstrip("/") + path, data=json.dumps(body or {}).encode(), method="PUT",
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read().decode("utf-8", "replace")


PIC_ON = set()   # screens (fpp|model) showing a kid's picture right now


def show_picture(item):
    """Put a kid's picture up on every screen at the same time; returns how many screens took it.
    Like the names: a screen that doesn't answer is skipped for a minute, and nobody waits on a slow one."""
    import threading
    data = picture(item)
    if not data:
        return 0
    results = []

    def one(sc):
        if DEAD.get(sc["fpp"], 0) > time.time() or NOPIC.get(sc["fpp"]):
            return
        try:
            fpp_put_image(sc["fpp"], sc["model"], data)
            results.append(sc["name"])
            PIC_ON.add((sc["fpp"], sc["model"]))
            DEAD.pop(sc["fpp"], None)
        except urllib.error.HTTPError as e:
            if e.code in (400, 404, 405, 501):
                NOPIC[sc["fpp"]] = True
                log("pictures can't go on", sc["name"], "(", sc["fpp"], "): it needs FPP 10 or newer - showing names only there")
            else:
                log("could not show the picture on", sc["name"], "-", e)
        except Exception as e:
            if DEAD.get(sc["fpp"], 0) <= time.time():
                log("could not reach", sc["name"], "(", sc["fpp"], ") - skipping it for a minute")
            DEAD[sc["fpp"]] = time.time() + 60
    threads = [threading.Thread(target=one, args=(sc,), daemon=True) for sc in S["screens"]]
    for t in threads:
        t.start()
    t0, first = time.time(), 0.0
    while any(t.is_alive() for t in threads) and time.time() - t0 < 4:
        if results and not first:
            first = time.time()
        if first and time.time() - first > 0.3:     # a screen has it: don't hold the break for a silent one
            break
        time.sleep(0.02)
    return len(results)


def clear_pictures():
    """Take the pictures down and hand the screens back to normal. A picture switches the overlay on,
    and FPP only switches it off after a name if the name switched it on - without this the last
    name would stay up over the show."""
    for host, model in list(PIC_ON):
        try:
            m = "/api/overlays/model/" + urllib.parse.quote(model)
            fpp_put(host, m + "/clear")
            fpp_put(host, m + "/state", {"State": 0})
        except Exception as e:
            log("could not clear the picture on", model, "-", e)
        PIC_ON.discard((host, model))


def say(item):
    """Start the spoken name (and the talking faces). Returns when to stop the faces, or 0."""
    name = voice_file(item)
    if not name:
        return 0
    try:
        os.utime(os.path.join(MUSIC, name))       # newest-used clips are kept
    except OSError:
        pass
    try:
        # its own stream slot, so the break's music (slot 1) keeps playing underneath
        fpp_command(MASTER, "Play Media", [name, 1, int(S["voice_volume"]), int(S["voice_slot"])])
    except Exception as e:
        log("could not play the spoken name:", e)
        return 0
    effs = talk_effects()
    if not effs:
        return 0
    started = 0
    for eff in effs:
        try:
            fpp_command(MASTER, "FSEQ Effect Start", [eff, "true", "false"])
            started += 1
        except Exception as e:
            log("could not start the talking effect '%s':" % eff, e)
    if not started:
        return 0
    try:
        length = float(item.get("say_for") or 2.0)
    except (TypeError, ValueError):
        length = 2.0
    return time.time() + max(0.5, min(8.0, length))


def talk_effects():
    """The talking faces effect name(s) from the settings file (commas allowed)."""
    return [e.strip()[:-5] if e.strip().lower().endswith(".eseq") else e.strip()
            for e in str(S["talk_effect"] or "").split(",") if e.strip()]


def stop_talking():
    for eff in talk_effects():
        try:
            fpp_command(MASTER, "FSEQ Effect Stop", [eff])
        except Exception as e:
            log("could not stop the talking effect:", e)


# ------------------------------------------------------------- screens ----
_info = {}


def screen_info(sc):
    """Overlay model size and a bold font for one screen (cached)."""
    k = sc["fpp"] + "|" + sc["model"]
    if k in _info:
        return _info[k]
    try:
        m = fpp_get(sc["fpp"], "/api/overlays/model/" + urllib.parse.quote(sc["model"]), timeout=2)
    except urllib.error.HTTPError as e:
        if e.code == 404:
            raise RuntimeError("no overlay model named '%s' on %s" % (sc["model"], sc["fpp"]))
        raise RuntimeError("FPP at %s answered %s" % (sc["fpp"], e.code))
    except (urllib.error.URLError, OSError) as e:
        raise RuntimeError("could not reach the FPP at %s (is it on, and is the address right?)" % sc["fpp"])
    if not m or not m.get("width"):
        raise RuntimeError("no overlay model named '%s' on %s" % (sc["model"], sc["fpp"]))
    font = S["font"]
    if not font:
        try:
            fonts = fpp_get(sc["fpp"], "/api/overlays/fonts") or []
        except Exception:
            fonts = []
        for want in ("DejaVuSans-Bold", "DejaVu-Sans-Bold", "Helvetica-Bold", "Arial-Bold",
                     "FreeSans-Bold", "LiberationSans-Bold"):
            if want in fonts:
                font = want
                break
        if not font:
            bold = [f for f in fonts if "bold" in f.lower()]
            font = bold[0] if bold else (fonts[0] if fonts else "")
    _info[k] = (int(m["width"]), int(m["height"]), font)
    return _info[k]


def layout(text, width, height):
    """Break after the comma (the name gets its own line), pick the biggest size that fits."""
    if ", " in text:
        a, b = text.split(", ", 1)
        a += ","
        wa, wb = a.split(" "), b.split(" ")
        options = [[a, b]]
        options += [[" ".join(wa[:i]), " ".join(wa[i:]), b] for i in range(1, len(wa))]
        options += [[a, " ".join(wb[:i]), " ".join(wb[i:])] for i in range(1, len(wb))]
    else:
        options = [[text]]
    best = None
    for lines in options:
        longest = max(len(l) for l in lines)
        size = min(100, int(float(S["max_width"]) * width / (0.68 * longest)), int(float(S["max_height"]) * height / (1.25 * len(lines))))
        key = (size, -len(lines), -longest)
        if best is None or key > best[0]:
            best = (key, (max(4, size), lines))
    return best[1]


SIZES = {"s": 0.45, "m": 0.6, "l": 0.8, "xl": 0.95}       # text height as a part of the screen's height
SPEEDS = {"slow": 0.75, "normal": 1.0, "fast": 1.35}


def text_plan(text, width, height, sc=None):
    """How one name goes up on one screen. Returns (size, position, pixels per second, text).
    Each screen's Text style / Text size / Scroll speed come from the website (Your screens).
    Automatic: small pixel matrices (64 pixels tall or less) get one big line that scrolls when it
    won't fit; TVs and projectors get still text, the name on its own line."""
    sc = sc or {}
    style = sc.get("style") or str(S.get("scroll") or "auto").replace("always", "scroll").replace("never", "still")
    size_k, speed_k = sc.get("size") or "auto", sc.get("speed") or "normal"
    small = height <= 64
    one = " ".join(text.split())
    if size_k == "auto" and not small and style != "scroll":
        size, lines = layout(text, width, height)        # TVs: the comfortable look, name on its own line
        return size, "Center", 0, "\\n".join(lines)
    if style == "auto" and not small:
        style = "still"                                  # TVs only scroll when you pick Scrolling
    px = int(sc.get("px") or 0) if size_k == "custom" else 0
    frac = (px / float(height)) if px else SIZES.get(size_k, 0.8 if small else 0.6)
    big = max(4, px) if px else max(6, min(200, int(height * frac)))
    fit = min(big, int(width * 0.95 / (0.62 * max(1, len(one)))))
    if style == "still" or (style == "auto" and fit >= max(6, 0.7 * big)):
        if fit >= (big if px else max(6, 0.7 * big)) or " " not in one:
            return max(4, fit), "Center", 0, one
        # too long for one line: break it up and fill the same height
        keep = S["max_width"], S["max_height"]
        S["max_width"], S["max_height"] = 0.95, frac
        try:
            size, lines = layout(text, width, height)
        finally:
            S["max_width"], S["max_height"] = keep
        return size, "Center", 0, "\\n".join(lines)
    secs = turn_secs(speed_k)
    pps = (width + 0.62 * big * len(one)) / secs
    return big, "R2L", max(10, int(round(pps))), one


def turn_secs(speed_k="normal"):
    """How long a scrolling name takes to cross the screen. Slow uses more of the name's turn, Fast less,
    but a name never runs into the next one: when it outlasts its turn, FPP clears the screen as it
    finishes and wipes out the name that just went up (said but never seen)."""
    base = max(2.0, float(S["show_for"]))
    want = base / SPEEDS.get(speed_k, 1.0)
    room = max(2.0, float(S["slot"]) - 0.4) if not live() else base + 0.8   # the whole turn, less a breath
    return max(1.5, min(want, room))


DEAD = {}      # FPP address -> until when it's skipped (it didn't answer: switched off, unplugged)


def show(text, color):
    """Put one name up on every screen at the same time; returns how many screens took it.
    A screen whose FPP doesn't answer is skipped for a minute, so one switched-off TV never
    holds up the names (or the voice) on the others."""
    import threading
    results = []

    def one(sc):
        if DEAD.get(sc["fpp"], 0) > time.time():
            return
        try:
            w, h, font = screen_info(sc)
            size, pos, pps, txt = text_plan(text, w, h, sc)
            dur = turn_secs(sc.get("speed") or "normal") if pps else min(float(S["show_for"]), turn_secs())
            args = [sc["model"], "Transparent", "Text", color, font, size, "true", pos, pps, round(dur, 2), txt]
            fpp_command(sc["fpp"], "Overlay Model Effect", args, timeout=2)
            results.append(sc["name"])
            DEAD.pop(sc["fpp"], None)
        except Exception as e:
            if isinstance(e, (urllib.error.URLError, OSError, socket.timeout)) and not isinstance(e, urllib.error.HTTPError) \
                    or "could not reach" in str(e):
                if DEAD.get(sc["fpp"], 0) <= time.time():
                    log("could not reach", sc["name"], "(", sc["fpp"], ") - skipping it for a minute")
                DEAD[sc["fpp"]] = time.time() + 60
            else:
                log("could not show on", sc["name"], "(", sc["fpp"], ") -", e)
            _info.pop(sc["fpp"] + "|" + sc["model"], None)
    threads = [threading.Thread(target=one, args=(sc,), daemon=True) for sc in S["screens"]]
    for t in threads:
        t.start()
    t0, first = time.time(), 0.0
    while any(t.is_alive() for t in threads) and time.time() - t0 < 4:
        if results and not first:
            first = time.time()
        if first and time.time() - first > 0.3:   # a screen has it: don't keep the voice waiting on a silent one
            break
        time.sleep(0.02)
    return len(results)


# --------------------------------------------------------------- break ----
BRK = {"t0": 0.0, "end": 0.0}


def bg_pull(done, st):
    """Check in with the website in the background, and download the waiting names' voices, so the
    FPP keeps watching the show (and catches the start of a names break) however slow the website is."""
    if BG["thread"] is not None:
        return False

    def run():
        try:
            d = pull(done, st) or {}
            if d.get("names") and not live():
                prefetch(d["names"])
            BG["result"] = d
        except Exception as e:
            BG["error"] = e
    BG.update(result=None, error=None, done=list(done), started=time.time())
    BG["thread"] = threading.Thread(target=run, daemon=True)
    BG["thread"].start()
    return True


def bg_take():
    """The finished background check-in's answer, or None while it's still going. A failed one hands back
    the shown names it was reporting; an answer from before the last break ended is stale and dropped."""
    t = BG["thread"]
    if t is None or t.is_alive():
        return None
    BG["thread"] = None
    if BG["error"] is not None:
        log("problem checking in with the website:", BG["error"], "- trying again shortly")
        return {"_failed": BG["done"]}
    if BG["started"] < BRK["end"]:
        return {}
    return BG["result"] or {}


def run_break(color):
    """Called when the break starts: fetch names, show them on the break's clock."""
    BRK["t0"] = time.time()
    keep = (S["names_start"], S["slot"], S["show_for"])
    try:
        return _run_break(color)
    finally:
        S["names_start"], S["slot"], S["show_for"] = keep


def _run_break(color):
    try:
        fresh = LATEST["data"] is not None and time.time() - LATEST["t"] < 90 and LATEST["t"] >= BRK["end"]
        data = LATEST["data"] if fresh else pull([])      # the names we already have: no waiting on the internet
        st = master_status()
        theme = current_theme(st)
        everyone = data.get("names") or []
        if theme:                                   # a themed sequence: its own timing, up to 6 names
            t = THEMES[theme]
            S["names_start"] = float(t.get("start") or S["names_start"])
            S["slot"] = float(t.get("slot") or S["slot"])
            S["show_for"] = max(2.0, S["slot"] - 0.6)
            slots = int(S["max_names"])
        else:
            slots = slots_for(st)
        per = int(S.get("per_break") or 6)
        if per < 6:
            # fewer names per break: the same stretch of the break, shared by fewer names, so each is up longer
            turns = max(1, int(round(slots * per / 6.0)))
            S["slot"] = float(S["slot"]) * slots / turns
            S["show_for"] = max(2.0, S["slot"] - 0.6)
            slots = turns
        names = (eligible(everyone, theme) or everyone)[:min(int(S["max_names"]), slots)]
        color = data.get("color") or color
    except Exception as e:
        log("break started but the website could not be reached:", e)
        return []
    log("break started%s with %d name(s), %d slot(s)" % ((" (theme: %s)" % THEMES[theme].get("name", theme)) if theme else "", len(names), slots))
    shown = _show_names(names, slots, color)
    # names that waited through this break go up in the next one, whatever theme it is
    for n in everyone:
        if n.get("id") not in shown:
            SKIPS[n.get("id")] = SKIPS.get(n.get("id"), 0) + 1
    for i in shown:
        SKIPS.pop(i, None)
    if len(SKIPS) > 500:
        SKIPS.clear()
    return shown


def _show_names(names, slots, color):
    prefetch(names)
    done = []
    talk_until = 0
    voice_until = 0.0                                  # when the name being said right now ends
    outro_at = float(S["names_start"]) + slots * float(S["slot"])    # the break audio's outro starts about here
    # A kid with a picture gets two turns: the picture (while the name is said), then the name.
    # Only whole kids that fit; the rest go up in the next break. Spare slots go round the names again.
    turns = []
    for item in names:
        need = 2 if picture(item) else 1
        if len(turns) + need > slots:
            break
        if need == 2:
            turns.append(("pic", item))
        turns.append(("name", item))
    first = len(turns)
    shown_items = [it for kind, it in turns if kind == "name"]
    while turns and len(turns) < slots:
        turns.append(("name", shown_items[(len(turns) - first) % len(shown_items)]))
    said = set()
    for k in range(len(turns)):
        kind, item = turns[k]
        at = float(S["names_start"]) + k * float(S["slot"])
        while True:
            if talk_until and time.time() >= talk_until:
                stop_talking()
                talk_until = 0
            st = master_status()
            if not in_break(st):
                log("break ended early")
                if talk_until:
                    stop_talking()
                if PIC_ON:
                    clear_pictures()
                return done
            el = elapsed(st)
            if el <= 0.05 and time.time() - BRK["t0"] > 0.5:
                el = time.time() - BRK["t0"] + 0.5   # no clock from FPP (a pause in the playlist): count it ourselves
            if el >= at:
                break
            time.sleep(0.1)
        late = el - at
        if late > 0.5 * float(S["slot"]) and k + 1 < len(turns):
            # running late (a slow website, a spoken name still downloading): skip this turn rather than
            # putting two names up at once and talking over the announcer; the name comes round again
            log("running %.1fs late - skipping turn %d" % (late, k + 1))
            continue
        if kind == "pic":
            if show_picture(item):
                log("showing the picture of:", item["text"])
            elif show(item["text"], color):              # no screen took the picture (an older FPP): the name now instead
                log("showing:", item["text"])
            else:
                continue
            if talk_until:
                stop_talking()
            talk_until = say(item)                       # the name is said while the picture is up
            try:
                voice_until = time.time() + max(0.5, min(8.0, float(item.get("say_for") or 2.0)))
            except (TypeError, ValueError):
                voice_until = time.time() + 2.0
            said.add(item["id"])
            continue
        if PIC_ON:
            clear_pictures()                             # the picture's turn is over: screens back to normal first
        if show(item["text"], color):
            log("showing:", item["text"])
            try:
                say_for = max(0.5, min(8.0, float(item.get("say_for") or 2.0)))
            except (TypeError, ValueError):
                say_for = 2.0
            repeat = k >= first
            # a name going round again is only said when Santa has finished the last one and it ends before the outro
            if item["id"] in said and not repeat:
                pass                                     # already said with the picture
            elif repeat and (time.time() < voice_until or el + say_for > outro_at - 0.3):
                pass
            elif S["say_every_time"] or not repeat:
                if talk_until:
                    stop_talking()
                talk_until = say(item)
                voice_until = time.time() + say_for
            if item["id"] not in done:
                done.append(item["id"])
        else:
            log("no screen took", item["text"], "- it will wait for the next break")
    if PIC_ON:
        clear_pictures()
    while in_break(master_status()):       # let the break finish
        if talk_until and time.time() >= talk_until:
            stop_talking()
            talk_until = 0
        time.sleep(0.2)
    if talk_until:
        stop_talking()
    return done


# ---------------------------------------------------------------- live ----
def show_live(names, color):
    """Live mode: put the waiting names up one after another while the show plays. No voice:
    it would talk over the music. Returns the ids shown."""
    keep = S["show_for"]
    S["show_for"] = float(MODE["show_for"] or S["live_show_for"])
    done = []
    try:
        for item in names[:int(S["max_names"])]:
            if status_name(master_status()) != "playing":
                break                                  # show stopped: the rest wait for it to start again
            if picture(item) and show_picture(item):
                log("showing the picture of (during the song):", item["text"])
                time.sleep(S["show_for"])
                clear_pictures()
            if show(item["text"], color):
                log("showing (during the song):", item["text"])
                done.append(item["id"])
                time.sleep(S["show_for"] + 1.0)
            else:
                log("no screen took", item["text"], "- trying again shortly")
                break
    finally:
        S["show_for"] = keep
    return done


# --------------------------------------------------------------- check ----
def run_checks(problems=None):
    """Every setup check, as a list of {ok, what, msg}. Used by --check and sent to the website at start."""
    out = []
    add = lambda ok, what, msg: out.append({"ok": bool(ok), "what": what, "msg": msg})
    for p in (problems or []):
        add(False, "settings", p)
    site_ok = False
    site_voice = False
    try:
        d = pull([])
        site_ok = True
        site_voice = bool(d.get("voice"))
        add(True, "website", "website and private key work (%d name(s) waiting)" % len(d.get("names") or []))
        bst = break_state()
        if d.get("break"):
            ok = str(bst.get("v")) == str(d["break"].get("v"))
            add(ok, "break", ("names break audio from the website is set up (built %s)" % bst.get("t", "")) if ok
                else "names break audio from the website isn't on this FPP yet (see the log)")
    except urllib.error.HTTPError as e:
        add(False, "website", "the private key is wrong - download marquee_names.json again" if e.code == 403 else "website answered %s %s" % (e.code, e.reason))
    except Exception as e:
        add(False, "website", "could not reach the website: %s" % e)
    if not S["screens"]:
        add(False, "screen", "no screens set yet: add them on your website (Marquee -> Settings -> Names & FPP -> Your screens)")
    st = master_status()
    if st:
        sn = status_name(st) or "?"
        if sn in ("playing", "paused"):
            add(True, "fpp", "FPP status: %s" % sn)
        else:   # FPP 10 says "playing media" when only audio/a single file runs: no playlist, so no names break
            add(True, "fpp", "FPP status: %s - names go up only while your show PLAYLIST is playing on this FPP (start it as a playlist, not a single sequence or Play Media)" % sn)
    else:
        add(False, "fpp", "could not read this FPP's status")
    made = ""
    try:
        made = ensure_simple_break()
    except Exception as e:
        add(False, "playlist", "could not make a simple names break on this FPP: %s" % e)
    try:
        pls = fpp_get(MASTER, "/api/playlists") or []
        if live():
            add(True, "playlist", "names go up during songs on the screens, so no names break playlist is needed")
        if S.get("only_playlists"):
            missing = [p for p in S["only_playlists"] if p.lower() not in [str(x).lower() for x in pls]]
            if missing:
                add(False, "playlist", "names only during: %s - no playlist named %s on this FPP" % (", ".join(S["only_playlists"]), ", ".join("'%s'" % m for m in missing)))
            else:
                add(True, "playlist", "names only during: %s" % ", ".join(S["only_playlists"]))
        if live():
            pass
        elif S["break_playlist"] in pls:
            short = [p for p in pls if p in ("%s 1" % S["break_playlist"], "%s 2" % S["break_playlist"])]
            if is_simple(fpp_playlist(S["break_playlist"])):
                add(True, "playlist", "playlist '%s' found - a simple names break Marquee made, no sequence needed%s" % (
                    S["break_playlist"], " (just made)" if made else ""))
            else:
                add(True, "playlist", "playlist '%s' found%s" % (S["break_playlist"], (" (short breaks: %s)" % ", ".join(short)) if short else ""))
            for t in THEMES.values():
                if t["playlist"] in pls:
                    add(True, "theme", "themed sequence '%s': playlist '%s' found" % (t.get("name", ""), t["playlist"]))
                else:
                    add(False, "theme", "themed sequence '%s': no playlist named '%s' on this FPP (those names go up in the classic break)" % (t.get("name", ""), t["playlist"]))
        else:
            add(False, "playlist", "no playlist named '%s' on this FPP (found: %s)%s" % (S["break_playlist"], ", ".join(pls) or "none",
                "" if S["simple_break"] else (" - make one with your names sequence, or turn on 'Make a simple names break for me' on your website"
                                               if LAST.get("site_simple") else " - make one with your names sequence (or update Marquee on your website: it can make a simple one for you)")))
    except Exception as e:
        add(False, "playlist", "could not list playlists: %s" % e)
    if S["voice"] and site_voice and not live():
        try:
            cmds = fpp_get(MASTER, "/api/commands") or []
            names = set(c.get("name") for c in cmds if isinstance(c, dict))
            if "Play Media" in names:
                add(True, "voice", "this FPP can play spoken names")
            else:
                add(False, "voice", "this FPP has no 'Play Media' command, so spoken names can't play (update FPP)")
            if talk_effects():
                effs = [str(e) for e in (fpp_get(MASTER, "/api/effects") or [])]
                for want in talk_effects():
                    if want in effs or want + ".eseq" in effs:
                        add(True, "voice", "talking effect '%s' found" % want)
                    else:
                        add(False, "voice", "no effect named '%s' on this FPP (found: %s)" % (want, ", ".join(effs) or "none"))
        except Exception as e:
            add(False, "voice", "could not check spoken-name support: %s" % e)
        if not os.access(MUSIC, os.W_OK):
            add(False, "voice", "can't save spoken names in %s" % MUSIC)
    for sc in S["screens"]:
        try:
            w, h, font = screen_info(sc)
            if w < h:
                add(False, "screen", "%s: overlay model '%s' is taller than wide (%d x %d) - strings and nodes are swapped in xLights" % (sc["name"], sc["model"], w, h))
            else:
                add(True, "screen", "%s: overlay model '%s' on %s is %d x %d, font %s" % (sc["name"], sc["model"], sc["fpp"], w, h, font or "none!"))
                better = fb_instead(sc, w, h)
                if better:
                    add(False, "screen", "%s: '%s' is the whole HDMI screen, so names blank your sequence there while they're up. "
                        "Use '%s' instead (same size, it's what your sequence draws on), and names go up on top of it" % (sc["name"], sc["model"], better))
        except Exception as e:
            msg = "%s (%s): %s" % (sc["name"], sc["fpp"], e)
            if "no overlay model" in str(e) and sc["fpp"].rstrip("/") == MASTER.rstrip("/").replace("://localhost", "://127.0.0.1"):
                ms = fpp_models() or []
                msg += " - models on this FPP: %s" % (", ".join(m["name"] for m in ms) or "none (upload your models with FPP Connect, Models ticked)")
            add(False, "screen", msg)
    return out, site_ok


def fb_instead(sc, w, h):
    """A screen set to a framebuffer (FB) model when that FPP also has a channel model the same size:
    the channel one is what the sequence draws on, so names on it go up over the sequence instead of blanking it."""
    try:
        models = fpp_get(sc["fpp"], "/api/overlays/models", timeout=3) or []
    except Exception:
        return ""
    mine = [m for m in models if isinstance(m, dict) and m.get("Name") == sc["model"]]
    if not mine or str(mine[0].get("Type", "")).upper() != "FB":
        return ""
    for m in models:
        if isinstance(m, dict) and str(m.get("Type", "")).lower() == "channel" and int(m.get("width") or 0) == w and int(m.get("height") or 0) == h:
            return str(m.get("Name"))
    return ""


def send_report(checks, req=""):
    try:
        pull([], None, {"v": VERSION, "checks": checks, "req": req})
    except Exception as e:
        log("could not send the setup check to the website:", e)


def check():
    print("\n=== Marquee names - setup check (v%s) ===\n" % VERSION)
    checks, site_ok = run_checks(load_settings())
    for c in checks:
        print(("[OK] " if c["ok"] else "[X]  ") + c["msg"])
    if site_ok:
        send_report(checks)
    if S["screens"]:
        print("\nShowing a test name on every screen for %s seconds..." % S["show_for"])
        show("MERRY CHRISTMAS!", "#FFFFFF")
    bad = [c for c in checks if not c["ok"]]
    print("\nAll good. Did MERRY CHRISTMAS! appear on your screens?" if not bad
          else "\nFix the [X] lines above, then run --check again. The setup guide explains each one.")


# --------------------------------------------------------------- setup ----
def ask(q, default=""):
    a = input("%s%s: " % (q, " [%s]" % default if default else "")).strip()
    return a or default


def setup():
    print("\n=== Marquee names - setup ===")
    print("Answer each question; press Enter to keep the value in [brackets].\n")
    load_settings()
    cur = dict(S)
    site = ask("Your website address (like https://yourshow.com)", cur["site"])
    if site and not site.startswith("http"):
        site = "https://" + site
    key = ask("Private key (Marquee -> Settings -> Names & FPP)", cur["key"])
    pl = ask("FPP playlist that holds your names break", cur["break_playlist"])
    screens = []
    print("\nScreens: each one is an FPP Pixel Overlay Model (matrix, projector, TV).")
    old = cur["screens"] or []
    i = 0
    while True:
        o = old[i] if i < len(old) else {}
        name = ask("Screen %d name (Enter with nothing to finish)" % (i + 1), o.get("name", "") if o else "")
        if not name:
            break
        host = ask("  FPP address for '%s' (like 192.168.1.50; this FPP = 127.0.0.1)" % name, o.get("fpp", "") if o else "127.0.0.1")
        model = ask("  Overlay model name on that FPP", o.get("model", "") if o else "")
        screens.append({"name": name, "fpp": host if host.startswith("http") else "http://" + host, "model": model})
        i += 1
    data = {k: cur[k] for k in DEFAULTS}
    data.update({"site": site, "key": key, "break_playlist": pl, "screens": screens})
    with open(config_path(), "w") as f:
        json.dump(data, f, indent=2)
    print("\nSaved %s. Running the setup check...\n" % config_path())
    check()


# ---------------------------------------------------------------- main ----
def cfg_stamp():
    try:
        return os.path.getmtime(config_path())
    except OSError:
        return 0
def other_copy():
    """Another names script running from a different place (the plugin's, or one put on the FPP by hand)."""
    me = os.path.abspath(__file__)
    try:
        for pid in os.listdir("/proc"):
            if not pid.isdigit() or int(pid) == os.getpid():
                continue
            try:
                args = open("/proc/%s/cmdline" % pid, "rb").read().split(b"\0")
            except OSError:
                continue
            args = [x.decode("utf-8", "replace") for x in args if x]
            if len(args) == 2 and os.path.basename(args[0]).startswith("python") \
                    and args[1].endswith("marquee_names.py") and os.path.abspath(args[1]) != me:
                return args[1]        # a running copy (not a --check or --test run)
    except OSError:
        pass
    return ""


def wait_until_ready(limit=180):
    """Right after a reboot the network and FPP itself take a minute to come up: wait for both
    (up to 3 minutes) so the first setup check isn't full of start-up errors."""
    host = urllib.parse.urlparse(str(S["site"])).hostname or ""
    t0, said = time.time(), False
    while time.time() - t0 < limit:
        try:
            socket.getaddrinfo(host, 443)
            fpp_get(MASTER, "/api/fppd/status", timeout=3)
            return
        except Exception:
            if not said:
                log("waiting for the network and FPP to finish starting...")
                said = True
            time.sleep(5)


def single_instance():
    other = other_copy()
    if other:
        log(("the names script you put on this FPP by hand is running (%s) - remove its Run Script command preset; "
             "the Marquee plugin runs it for you now - exiting" if PLUGIN else
             "the Marquee plugin already runs the names script (%s) - you can delete this copy and its Run Script command preset - exiting") % other)
        sys.exit(0)
    try:
        if os.path.exists(LOCK):
            old = int(open(LOCK).read().strip() or 0)
            if old and old != os.getpid() and os.path.exists("/proc/%d" % old):
                log("already running (pid %d) - exiting" % old)
                sys.exit(0)
        open(LOCK, "w").write(str(os.getpid()))
    except (OSError, ValueError):
        pass


def main():
    if len(sys.argv) > 1 and sys.argv[1] == "--setup":
        setup()
        return
    if len(sys.argv) > 1 and sys.argv[1] == "--check":
        check()
        return
    if len(sys.argv) > 2 and sys.argv[1] == "--test":
        load_settings()
        try:
            pull([])                      # your screens come from the website
        except Exception as e:
            print("could not reach the website:", e)
        print("shown on %d screen(s)" % show(sys.argv[2].upper(), "#FFFFFF"))
        return
    single_instance()
    problems = load_settings()
    while problems and not (S["site"] and S["key"]):
        for p in problems:
            log("setup problem:", p)
        time.sleep(60)                        # wait for the settings file, check again in a minute
        problems = load_settings()
    wait_until_ready()
    log("started v%s; %s; screens: %s" % (
        VERSION, "names during songs" if live() else "break playlist '%s'" % S["break_playlist"], ", ".join("%s=%s on %s" % (s["name"], s["model"], s["fpp"]) for s in S["screens"]) or "none"))
    checks, site_ok = run_checks(problems)
    for c in checks:
        log(("ok: " if c["ok"] else "PROBLEM: ") + c["msg"])
    if site_ok:
        send_report(checks)
    if CHECK["want"] == "changed":      # the first check already used the website's settings
        CHECK["want"] = ""
    simple_t = time.time()
    done = []
    queued = QUEUED['on'] = False
    last_check = 0.0
    color = "#FFFFFF"
    idle_said = 0.0
    other_said = 0.0
    every = float(S["check_every"])
    cfg_seen = cfg_stamp()
    me_seen = self_stamp()
    while True:
        try:
            if self_stamp() != me_seen and self_stamp():
                time.sleep(2)                 # let the copy or update finish writing it
                restart_self("names script updated - starting the new version")
            if os.path.exists(os.path.join(DATA, "restart")) or cfg_stamp() != cfg_seen:
                try:
                    os.remove(os.path.join(DATA, "restart"))
                except OSError:
                    pass
                restart_self("settings changed - restarting")
            st = master_status()
            count_songs(st)
            if not in_break(st):
                if CHECK["want"]:                                   # "Check again" on the website, or settings changed there
                    req = "" if CHECK["want"] == "changed" else CHECK["want"]
                    CHECK["want"] = ""
                    if req:
                        CHECK["done"] = req                          # so the check-ins during the check don't ask again
                    checks, site_ok = run_checks(load_settings_keep())
                    log("setup check%s: %s" % (" (asked for on the website)" if req else " (settings changed)",
                        "all good" if all(c["ok"] for c in checks) else "; ".join(c["msg"] for c in checks if not c["ok"])))
                    if site_ok:
                        send_report(checks, req)
                    if req:
                        CHECK["done"] = req
                    PL_SENT["t"] = 0                                # send the FPP's playlists and models again too
                    simple_t = time.time()
                    last_check = 0.0
                if TESTS["want"]:                                   # "Send a test name" on the website
                    t = TESTS["want"]
                    TESTS["want"] = None
                    TESTS["done"].add(str(t["id"]))
                    text = str(t.get("text") or "TEST")[:40]
                    n = show(text, "#FFFFFF")
                    log("test name %r from the website shown on %d screen(s)" % (text, n))
                    TESTS["results"].append({"id": str(t["id"]), "screens": n, "of": len(S["screens"]), "t": int(time.time())})
                    last_check = 0.0
                if time.time() - simple_t > 300:                     # the simple names break follows timing changes
                    simple_t = time.time()
                    try:
                        ensure_simple_break()
                    except Exception as e:
                        log("could not update the simple names break:", e)
            if in_break(st):
                if BG["thread"] is not None:                  # don't wait for a check-in that's still going
                    BG["thread"].join(0)
                done += run_break(color)
                BRK["end"] = time.time()
                SONGS.update(key=None, played=0)          # start counting songs again
                queued = QUEUED['on'] = False
                try:
                    pull(done)            # report what was shown (the next break must not show them again)
                    done = []
                except Exception as e:
                    log("could not tell the website which names were shown:", e, "- trying again shortly")
                last_check = time.time()
                continue
            res = bg_take()
            if res is not None and "_failed" in res:
                done = res["_failed"] + done
                res = None
            if status_name(st) != "playing":
                queued = QUEUED['on'] = False
                if res:
                    waiting = len(res.get("names") or [])
                    if waiting and time.time() - idle_said >= 600:   # say why names wait, every 10 minutes at most
                        idle_said = time.time()
                        log("%d name(s) waiting, but FPP is '%s', not playing a playlist - names go up once your show playlist plays on this FPP"
                            % (waiting, status_name(st) or "?"))
                if done or time.time() - last_check >= (5 if SUPPORT["on"] or time.time() < FAST["until"] else 60):   # idle: once a minute (every 5 s while someone is setting up or in support)
                    if bg_pull(done, st):
                        last_check = time.time()
                        done = []
                time.sleep(0.5)          # watch closely: a names break started by hand is caught within half a second
                continue
            adv = ADVISORY["now"]
            if adv and adv.get("text") and time.time() - ADVISORY["last"] >= float(adv.get("every") or 180):
                ADVISORY["last"] = time.time()
                keep = S["show_for"]
                S["show_for"] = 6
                try:
                    if show(str(adv["text"])[:60], "#FFCC33"):
                        log("traffic alert on the screens:", adv["text"])
                finally:
                    S["show_for"] = keep
            if time.time() - last_check >= (min(every, 5) if SUPPORT["on"] or time.time() < FAST["until"] else every):
                if bg_pull(done, st):
                    last_check = time.time()
                    done = []
            if res is not None:
                data = res
                color = data.get("color") or color
                waiting = len(data.get("names") or [])
                if waiting and not names_allowed(st):
                    if time.time() - other_said >= 600:   # say why names wait, every 10 minutes at most
                        other_said = time.time()
                        log("%d name(s) waiting - '%s' isn't one of your names playlists (%s), so they wait for it"
                            % (waiting, playlist_name(st) or "?", ", ".join(S["only_playlists"])))
                    time.sleep(1)
                    continue
                if waiting and live():
                    shown = show_live(data.get("names") or [], color)
                    if shown:
                        done += shown                     # reported at the next check-in, right away
                    last_check = time.time() - every + 2  # check again soon for more
                    continue
                if waiting and not queued and not break_due(waiting):
                    if time.time() - other_said >= 600:
                        other_said = time.time()
                        log("%d name(s) waiting - names go up after every %d songs (%d played since the last break)"
                            % (waiting, int(S["every"]), SONGS["played"]))
                elif waiting and not queued:
                    pl = queue_break(data.get("names") or [])
                    queued = QUEUED['on'] = True
                    log("%d name(s) waiting - '%s' queued after the current song" % (waiting, pl))
            time.sleep(0.25 if queued else 1)   # a break is coming: catch its start within a quarter second
        except Exception as e:
            log("problem:", e, "- trying again shortly")
            time.sleep(every)


if __name__ == "__main__":
    main()
