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

VERSION = "3.16.0"
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
    "own_start": 0,                   # your own break audio: seconds in when the intro ends (first name goes up); 0 = not set
    "own_end": 0,                     # ... and when the closing lines start. Names are said only in between;
                                      #   after that the screens show names already said, with no voice
    "per_break": 6,                   # names in one break: fewer = each is up longer (spread over the same part of the break)
    "every": 1,                       # a names break after every this many songs (names collect in between)
    "music_slot": 1,                  # FPP media stream slot for the names break's own music (the break with no fixed timing)
    "flow": True,                     # Marquee's simple break: run it with no fixed timing when the website sent its parts
}
MODE = {"mode": None, "show_for": None}   # what the website said last (it wins over the settings file)
ADVISORY = {"now": None, "last": 0.0}     # traffic & parking alert from Show Control, put on the screens now and then


def live():
    return (MODE["mode"] or S["mode"]) == "live"

S = dict(DEFAULTS)


LOGBUF = []          # the last log lines, for a Marquee Support session


_LAST_LOG = {"msg": "", "t": 0.0, "n": 0}


def log(*a):
    msg = " ".join(str(x) for x in a)
    now = time.time()
    if msg == _LAST_LOG["msg"] and now - _LAST_LOG["t"] < 60:   # the same line over and over (FPP busy): once a minute
        _LAST_LOG["n"] += 1
        return
    if _LAST_LOG["n"]:
        msg_n = _LAST_LOG["n"]
        _LAST_LOG["n"] = 0
        _write_log("(the line above repeated %d more time%s)" % (msg_n, "" if msg_n == 1 else "s"))
    _LAST_LOG.update({"msg": msg, "t": now})
    _write_log(msg)


def _write_log(msg):
    line = time.strftime("%Y-%m-%d %H:%M:%S ") + msg
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


SITE_KEYS = ("break_playlist", "names_start", "slot", "show_for", "talk_effect", "scroll", "own_start", "own_end")


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
    sent = list(BREAKS["pending"])
    if sent:
        data["breaks"] = json.dumps(sent)              # 3.16+: what each names break did, for the website
    res = http_json(url, data)
    if sent and isinstance(res, dict) and res.get("success"):
        BREAKS["pending"] = [b for b in BREAKS["pending"] if b not in sent]
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
        VOICE_ON["site"] = bool(res["data"].get("voice"))
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


BREAKS = {"pending": []}    # what each names break did, waiting to be sent to the website


def break_report(kind, rows, note=""):
    """Remember what a names break did (each name: shown or not, said or why not) for the website."""
    BREAKS["pending"].append({"t": int(time.time()), "kind": kind, "names": rows[:12], "note": note[:200]})
    del BREAKS["pending"][:-10]


# --------------------------------------------------- break from website ----
STATE = os.path.join(DATA, "marquee_break_state.json")
BREAK_FILES = {"full": "marquee-break-full.wav", "short2": "marquee-break-2.wav", "short1": "marquee-break-1.wav"}
# 3.16+: the break's parts, for Marquee's simple break with no fixed timing: the intro (music comes in and the
# announcer's first line), soft music for under the names, and the closing (the announcer's last line, music out)
PART_FILES = {"intro": "marquee-break-intro.wav", "bed": "marquee-break-bed.wav", "outro": "marquee-break-outro.wav"}


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
    flow = None
    if simple and all(b.get(k) for k in PART_FILES) and float(b.get("intro_secs") or 0) > 0 and float(b.get("outro_secs") or 0) > 0:
        try:
            for k, name in PART_FILES.items():
                download(b[k], os.path.join(MUSIC, name))
            flow = {"intro": float(b["intro_secs"]), "outro": float(b["outro_secs"]), "bed": float(b.get("bed_secs") or 60)}
        except Exception as e:
            log("could not download the names break parts (the break keeps its fixed timing):", e)
    if simple:                                  # Marquee's own names break: it just plays the new audio
        st.update({"v": str(b["v"]), "start": float(b.get("start") or S["names_start"]), "slot": float(b.get("slot") or S["slot"]),
                   "t": time.strftime("%Y-%m-%d %H:%M"), "simple": True})
        if flow:
            st["flow"] = flow
        else:
            st.pop("flow", None)
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
    return "%s|%s|%s|%s|%s" % ((st.get("v") or "") if st.get("simple") else "", float(S["names_start"]), float(S["slot"]), int(S["max_names"]),
                               "flow" if flow_ready(st) else "")


FLOW = {"on": False}     # a names break with no fixed timing is running right now


def flow_ready(st=None):
    """Marquee's simple break, with its parts from the website on this FPP: the script runs the break itself
    (intro, each name for as long as it needs, closing) instead of fitting names into fixed-length audio."""
    st = break_state() if st is None else st
    if not (S.get("flow") and S["simple_break"] and st.get("simple") and st.get("flow")) or S.get("break_own") or live():
        return False
    return all(os.path.exists(os.path.join(MUSIC, f)) for f in PART_FILES.values())


def save_flow_break(total):
    """The simple break playlist: a pause as long as this break (the show waits while the names go up)."""
    bp = S["break_playlist"]
    save_playlist(bp, {"name": bp, "desc": SIMPLE_DESC, "repeat": 0, "loopCount": 0, "leadIn": [], "leadOut": [],
                       "mainPlaylist": [{"type": "pause", "enabled": 1, "playOnce": 0, "duration": round(float(total), 1)}]})
    _playlists["t"] = 0


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
    flow = flow_ready(st)
    for key, pl, slots in (("full", bp, int(S["max_names"])), ("short2", bp + " 2", 4), ("short1", bp + " 1", 2)):
        if flow and pl == bp:
            save_flow_break(st.get("flow_total") or 30)
            continue
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
    if flow:
        msg = "made a simple names break: '%s' - the show pauses, the intro plays, each name goes up for as long as it needs, then the closing" % bp
    else:
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


def break_length(st):
    """How long the break that is playing runs, in seconds (0 when FPP doesn't say)."""
    try:
        rem = float(st.get("seconds_remaining") or 0)
    except (TypeError, ValueError):
        rem = 0.0
    return elapsed(st) + rem if rem > 0 else 0.0


QUIET = {"until": 0.0}   # seconds into the break when the break's own voice (Santa's outro) starts


def slots_for(st):
    """Name spots in the break that is playing. Counted from the break's own length, so a sequence
    made with room for 3, 5 or 6 names gets every spot filled; a short break without a length that
    FPP reports says each of its 1 or 2 names twice."""
    want = int(S["max_names"])
    short = 0
    for n, name in short_playlists().items():
        if playlist_name(st) == name:
            want = n * 2
            short = want
    a, b = own_window()
    if b:
        # your own break audio: the names go in the quiet part between its intro and its closing,
        # as many as fit (about 3.3 seconds each), evenly spread
        n = max(1, int((b - a + 0.3) / 3.3))
        S["names_start"], S["slot"] = a, (b - a) / n
        S["show_for"] = max(2.0, S["slot"] - 0.6)
        want = n
    slots = want
    length = break_length(st)
    if length:
        fit = int((length - float(S["names_start"]) - 1.0) / float(S["slot"]))   # a second or so left for the outro
        if fit >= 1:
            slots = min(int(S["max_names"]), fit)
    if short:
        # a short break has room for each name twice and no more: its length also holds the announcer's
        # closing (several seconds, not one), so counting from the length would put names over it
        slots = min(slots, short)
    # names are said only up to the closing; any spots after it show names already said, no voice
    QUIET["until"] = b if b else float(S["names_start"]) + slots * float(S["slot"])
    return slots


def own_window():
    """(first name at, closing starts at) for a show's own break audio, or (0, 0) when not set."""
    try:
        a, b = float(S.get("own_start") or 0), float(S.get("own_end") or 0)
    except (TypeError, ValueError):
        return 0.0, 0.0
    if a <= 0 or b < a + 2.5:
        return 0.0, 0.0
    return a, b


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
    # a kid with a picture needs two turns (picture, then name), so counts as two:
    # one picture kid gets "Names in Lights 2" and goes up twice, like a name on its own
    need = sum(2 if (n.get("photo") and not NOPIC_ALL()) else 1 for n in eligible(names, ""))
    return short_playlists().get(need, S["break_playlist"])


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
    # the short breaks for 1 or 2 names have to exist before picking one (just after switching to a
    # names break between songs they may not yet, and the full break would repeat 2 names to fill it)
    try:
        ensure_simple_break()
    except Exception as e:
        log("could not make the simple names break:", e)
    # "ifNotRunning" = true so a second request never stacks a second break
    pl = plan_break(names or [])
    bp = S["break_playlist"]
    if pl in (bp, bp + " 1", bp + " 2") and flow_ready():
        try:
            if is_simple(fpp_playlist(bp)):
                total = plan_flow(names or [])[1]
                save_flow_break(total)
                st = break_state()
                st["flow_total"] = total
                save_state(st)
                pl = bp
        except Exception as e:
            log("could not set the names break's length, using its fixed timing -", e)
    fpp_command(MASTER, "Insert Playlist After Current", [pl, -1, -1, "true"])
    return pl


# ------------------------------------------------ break with no fixed timing ----
FLOW_MAX_NAMES_SECS = 48.0     # the most time the names get in one break; anyone left goes up in the next


def turn_secs(item):
    """How long one kid's turn is in a break with no fixed timing: the picture while the name is said (if any),
    then the name, up long enough to be read and to be said, never less than 3 seconds."""
    try:
        say_for = max(0.5, min(8.0, float(item.get("say_for") or 2.0))) if (item.get("audio") and S["voice"]) else 0.0
    except (TypeError, ValueError):
        say_for = 2.0
    pic = bool(item.get("photo")) and not NOPIC_ALL()
    name = max(3.0, need_secs(item.get("text", "")) + 0.4, 0.0 if pic else say_for + 1.0)
    return (max(3.0, say_for + 1.0) if pic else 0.0) + name, pic, say_for, name


def plan_flow(names):
    """Which names go in the next break with no fixed timing, and how long the whole break is."""
    f = break_state().get("flow") or {}
    go = eligible(names, "") or list(names)
    most = min(int(S["max_names"]), int(S.get("per_break") or 6))
    plan, used = [], 0.0
    for item in go[:most]:
        t = turn_secs(item)[0]
        if plan and used + t > FLOW_MAX_NAMES_SECS:
            break
        plan.append(item)
        used += t
    total = float(f.get("intro") or 4) + used + float(f.get("outro") or 5) + 1.2
    return plan, round(total, 1)


def music(part, stop=False):
    try:
        if stop:
            fpp_command(MASTER, "Stop Media", [PART_FILES[part]], timeout=2)
        else:
            fpp_command(MASTER, "Play Media", [PART_FILES[part], 1, 0, int(S["music_slot"])], timeout=3)
    except Exception as e:
        if not stop:
            log("could not play the names break %s:" % part, e)


def _run_flow(color):
    """Marquee's simple break, with no fixed timing: intro, then each name for as long as it needs, then the
    closing. The show waits on a pause made just as long as this break when it was lined up."""
    st0 = break_state()
    f = st0.get("flow") or {}
    intro, outro = float(f.get("intro") or 4), float(f.get("outro") or 5)
    total = float(st0.get("flow_total") or 30)
    try:
        fresh = LATEST["data"] is not None and time.time() - LATEST["t"] < 90 and LATEST["t"] >= BRK["end"]
        data = LATEST["data"] if fresh else pull([])
        color = data.get("color") or color
        everyone = data.get("names") or []
    except Exception as e:
        log("break started but the website could not be reached:", e)
        everyone = []
    go = eligible(everyone, "") or everyone
    room = total - intro - outro - 1.2           # the names' share of the pause the show is waiting on
    plan, used = [], 0.0
    for item in go[:min(int(S["max_names"]), int(S.get("per_break") or 6))]:
        t = turn_secs(item)
        if plan and used + t[0] > room + 0.3:
            break
        plan.append((item, t))
        used += t[0]
    CUR.update(kind="flow", note="")
    log("break started with %d name(s) (no fixed timing: intro %.1fs, names %.1fs, closing %.1fs)" % (len(plan), intro, used, outro))
    prefetch([p[0] for p in plan])
    rows, done = {}, []
    t0 = BRK["t0"]
    talk = {"until": 0.0}

    def wait_until(t):
        last = 0.0
        while time.time() < t:
            if talk["until"] and time.time() >= talk["until"]:
                stop_talking()
                talk["until"] = 0.0
            if time.time() - last > 0.5:
                last = time.time()
                if not in_break(master_status()):
                    return False
            time.sleep(0.1)
        return True

    def speak(item):
        if talk["until"]:
            stop_talking()
        talk["until"] = say(item)
        _mark(rows, item, said=True)

    FLOW["on"] = True
    early = {"on": False}
    keep = S["show_for"]
    try:
        music("intro")
        if not wait_until(t0 + intro):
            log("break ended early")
            early["on"] = True
            return done
        music("bed")
        at = t0 + intro
        for item, (turn, pic, say_for, name_secs) in plan:
            if pic:
                if PIC_ON or HELD:
                    clear_pictures()
                if show_picture(item):
                    log("showing the picture of:", item["text"])
                    _mark(rows, item, shown=True)
                    speak(item)
                    at += max(3.0, say_for + 1.0)
                    if not wait_until(at):
                        log("break ended early")
                        early["on"] = True
                        return done
                else:
                    pic = False                          # no screen took the picture: the name, said, instead
            if HELD:
                clear_pictures(names_only=True)
            S["show_for"] = max(2.0, name_secs - 0.3)
            if show(item["text"], color):
                log("showing:", item["text"])
                _mark(rows, item, shown=True)
                if not pic:
                    speak(item)
                if item["id"] not in done:
                    done.append(item["id"])
            else:
                log("no screen took", item["text"], "- it will wait for the next break")
                rows.setdefault(item.get("id"), {}).setdefault("voice", "no screen took it")
            at += name_secs
            if not wait_until(at):
                log("break ended early")
                early["on"] = True
                return done
        if PIC_ON or HELD:
            clear_pictures()
        music("bed", stop=True)
        music("outro")
        wait_until(time.time() + outro)
        while in_break(master_status()) and time.time() < t0 + total + 15:   # let the pause finish
            time.sleep(0.2)
        return done
    finally:
        FLOW["on"] = False
        S["show_for"] = keep
        if talk["until"]:
            stop_talking()
        if PIC_ON or HELD:
            clear_pictures()
        music("bed", stop=True)
        if early["on"]:
            music("intro", stop=True)
            music("outro", stop=True)
        out = []
        for item in go[:12]:
            r = rows.get(item.get("id")) or {}
            out.append({"text": item.get("text", ""), "shown": bool(r.get("shown")),
                        "voice": r.get("voice") or ("" if r.get("shown") else "waits for the next break")})
        break_report("flow", out)


# --------------------------------------------------------------- voice ----
def voice_file(item):
    """Local file name for a name's spoken clip, downloading it if needed. None = no voice for this name."""
    url = item.get("audio") if S["voice"] else None
    if not url or not str(url).startswith(("http://", "https://")):
        SAY["why"] = "no voice clip from the website" if S["voice"] and VOICE_ON["site"] else "voice off"
        return None
    base = os.path.basename(urllib.parse.urlparse(url).path)
    if not base.endswith((".mp3", ".wav")) or "/" in base or base.startswith("."):
        SAY["why"] = "the website sent a voice clip that isn't audio"
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
        SAY["why"] = "couldn't download the voice clip (%s)" % str(e)[:80]
        return None


SAY = {"why": "", "n": 0}            # why the last name wasn't said; a counter for the play copies
VOICE_ON = {"site": False}           # the website has spoken names on
PLAY_PREFIX = "marquee-say-"


def play_copy(name):
    """A copy of a spoken clip under a name FPP hasn't played before. FPP can stay silent when it's asked
    to play the same file again later (the second time a kid's name came round), so every play gets its
    own name. It's a hard link: no extra space, and it's made in an instant. Old ones are tidied away."""
    SAY["n"] += 1
    new = "%s%d-%d-%s" % (PLAY_PREFIX, int(time.time()), SAY["n"], name[len(VOICE_PREFIX):] if name.startswith(VOICE_PREFIX) else name)
    src, dst = os.path.join(MUSIC, name), os.path.join(MUSIC, new)
    try:
        try:
            os.link(src, dst)
        except OSError:
            import shutil
            shutil.copyfile(src, dst)
    except OSError as e:
        log("could not make a fresh copy of the spoken name, playing the saved one -", e)
        return name
    try:
        cut = time.time() - 600
        for f in os.listdir(MUSIC):
            if f.startswith(PLAY_PREFIX) and f != new and os.path.getmtime(os.path.join(MUSIC, f)) < cut:
                os.remove(os.path.join(MUSIC, f))
    except OSError:
        pass
    return new


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
HELD = set()     # screens where we keep the overlay on for a name that went up over a picture


def NOPIC_ALL():
    """True when no screen can take pictures (all older than FPP 10)."""
    return bool(S["screens"]) and all(NOPIC.get(sc["fpp"]) for sc in S["screens"])


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


def clear_pictures(names_only=False):
    """Hand the screens back to normal after a picture or a name that went up over one: blank the
    overlay, then switch it off. Blank first, so the next name never flashes the old picture while
    FPP draws it. A short wait after, because FPP drops any effect a moment after a blank."""
    hit = False
    for host, model in list(HELD if names_only else (PIC_ON | HELD)):
        m = "/api/overlays/model/" + urllib.parse.quote(model)
        try:
            fpp_put(host, m + "/fill", {"RGB": [0, 0, 0]})
        except Exception as e:
            log("could not blank the picture on", model, "-", e)
        try:
            fpp_put(host, m + "/state", {"State": 0})
        except Exception as e:
            log("could not switch off the picture on", model, "-", e)
        PIC_ON.discard((host, model))
        HELD.discard((host, model))
        hit = True
    if hit:
        time.sleep(0.15)


def say(item):
    """Start the spoken name (and the talking faces). Returns when to stop the faces, or 0."""
    SAY["why"] = ""
    name = voice_file(item)
    if not name:
        SAY["last"] = "" if SAY["why"] == "voice off" else "not said: " + (SAY["why"] or "no voice clip")
        if SAY["why"] not in ("voice off", ""):
            log("not saying %s - %s" % (item.get("text"), SAY["why"]))
        return 0
    try:
        os.utime(os.path.join(MUSIC, name))       # newest-used clips are kept
    except OSError:
        pass
    play = play_copy(name)
    try:
        # its own stream slot, so the break's music (slot 1) keeps playing underneath
        fpp_command(MASTER, "Play Media", [play, 1, int(S["voice_volume"]), int(S["voice_slot"])])
    except Exception as e:
        log("could not play the spoken name:", e)
        SAY["last"] = "not said: FPP didn't take the play command (%s)" % str(e)[:80]
        return 0
    SAY["last"] = "said"
    log("saying:", item.get("text"))
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


def layout(text, width, height, one_line=False, cap=100):
    """Break after the comma (the name gets its own line), pick the biggest size that fits.
    one_line: one line is also a choice (still text on a pixel matrix), and text with no comma
    can go on two lines, split where the two lines come out most even."""
    text = " ".join(text.split())
    if ", " in text:
        a, b = text.split(", ", 1)
        a += ","
        wa, wb = a.split(" "), b.split(" ")
        options = [[a, b]]
        options += [[" ".join(wa[:i]), " ".join(wa[i:]), b] for i in range(1, len(wa))]
        options += [[a, " ".join(wb[:i]), " ".join(wb[i:])] for i in range(1, len(wb))]
    else:
        w = text.split(" ")
        options = [[text]]
        if one_line and len(w) > 1:
            options += [[" ".join(w[:i]), " ".join(w[i:])] for i in range(1, len(w))]
    if one_line and [text] not in options:
        options.append([text])
    best = None
    for lines in options:
        longest = max(len(l) for l in lines)
        tall = 1.0 if (one_line and len(lines) == 1) else 1.25 * len(lines)   # room between lines
        cw = 0.72 if one_line else 0.68     # a capital letter in a bold font is about 0.7 of the size wide
        size = min(cap, int(float(S["max_width"]) * width / (cw * longest)), int(float(S["max_height"]) * height / tall))
        key = (size, -len(lines), -longest)
        if best is None or key > best[0]:
            best = (key, (max(4, size), lines))
    return best[1]


SIZES = {"s": 0.45, "m": 0.6, "l": 0.8, "xl": 0.95}       # text height as a part of the screen's height
READ = {"slow": 5.0, "normal": 6.5, "fast": 8.0}         # letters a second people read comfortably as a name scrolls by
CW = 0.7                                                  # a bold capital letter is about 0.7 of the font size wide
STILL_SECS = 2.2                                          # still text: the least time to read the whole message


def still_text(text, width, height, cap):
    """Still text: one line, or two or three centered lines (the name on its own line after the comma),
    whichever lets the letters be biggest, never bigger than cap. Returns (size, text)."""
    one = " ".join(text.split())
    if " " not in one:
        return max(4, min(cap, int(width * 0.95 / (CW * max(1, len(one)))), int(height * 0.95))), one
    keep = S["max_width"], S["max_height"]
    S["max_width"], S["max_height"] = 0.95, 0.95
    try:
        size, lines = layout(one, width, height, one_line=True, cap=cap)
    finally:
        S["max_width"], S["max_height"] = keep
    return size, "\n".join(lines)   # a real line break: FPP 9 and 10 both draw it


def text_plan(text, width, height, sc=None, room=None):
    """How one name goes up on one screen. Returns (size, position, pixels per second, text, seconds it needs).
    Each screen's Text / Size / Scroll speed come from the website (Your screens).
    Automatic on a pixel matrix (64 pixels tall or less): still text on one to three lines when the
    letters come out big enough to read (8 pixels and a fifth of the screen's height or more), otherwise
    one big line that scrolls at a speed people can read. TVs and projectors: still text.
    room: the seconds this name has in a timed break. A scrolling name only goes faster than reading
    speed when it has to, to get across in its turn."""
    sc = sc or {}
    style = sc.get("style") or str(S.get("scroll") or "auto").replace("always", "scroll").replace("never", "still")
    size_k, speed_k = sc.get("size") or "auto", sc.get("speed") or "normal"
    small = height <= 64
    one = " ".join(text.split())
    if size_k == "auto" and not small and style != "scroll":
        size, lines = layout(text, width, height)        # TVs: the comfortable look, name on its own line
        return size, "Center", 0, "\n".join(lines), STILL_SECS
    if style == "auto" and not small:
        style = "still"                                  # TVs only scroll when you pick Scrolling
    px = int(sc.get("px") or 0) if size_k == "custom" else 0
    frac = (px / float(height)) if px else SIZES.get(size_k, 0.8 if small else 0.6)
    big = max(4, px) if px else max(6, min(200, int(height * frac)))
    if style in ("still", "auto"):
        size, txt = still_text(text, width, height, big)
        if style == "still" or size >= max(8, int(0.2 * height)):
            return size, "Center", 0, txt, STILL_SECS
    dist = width + CW * big * len(one)                   # the whole message, from coming in to gone
    pps = READ.get(speed_k, READ["normal"]) * CW * big
    if room:
        pps = max(pps, dist / max(1.5, room))
    return big, "R2L", max(10, int(round(pps))), one, round(dist / pps + 0.3, 2)


def turn_room():
    """Seconds a name has on the screens: its turn in a timed break (less a breath), a live-mode turn,
    or None in a break that gives each name the time it needs."""
    if FLOW["on"]:
        return None
    if live():
        return max(2.0, float(S["show_for"])) + 0.8
    return max(2.0, float(S["slot"]) - 0.4)


def need_secs(text):
    """How long a message needs on the screens to be read, on the slowest of them."""
    need = STILL_SECS
    for sc in S["screens"]:
        try:
            w, h, _ = screen_info(sc)
            need = max(need, text_plan(text, w, h, sc)[4])
        except Exception:
            pass
    return need


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
            room = turn_room()
            size, pos, pps, txt, need = text_plan(text, w, h, sc, room)
            dur = need if pps else (min(float(S["show_for"]), room) if room else float(S["show_for"]))
            args = [sc["model"], "Transparent", "Text", color, font, size, "true", pos, pps, round(dur, 2), txt]
            key = (sc["fpp"], sc["model"])
            fpp_command(sc["fpp"], "Overlay Model Effect", args, timeout=2)
            if key in PIC_ON:
                # The picture is still up and the overlay stays on: the name replaces it straight away,
                # with no off-and-on in between (that was the flicker). Then see-through like any name.
                # FPP won't switch it off by itself this time, so clear_pictures() does after the turn.
                try:
                    fpp_put(sc["fpp"], "/api/overlays/model/" + urllib.parse.quote(sc["model"]) + "/state", {"State": 2})
                except Exception as e:
                    log("could not make the name see-through on", sc["name"], "-", e)
                PIC_ON.discard(key)
                HELD.add(key)
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
    if flow_ready() and playlist_name(master_status()) == S["break_playlist"]:
        return _run_flow(color)
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
        if per < 6 and not own_window()[1]:
            # fewer names per break: the same stretch of the break, shared by fewer names, so each is up longer
            turns = max(1, int(round(slots * per / 6.0)))
            short = [n for n, name in short_playlists().items() if name == playlist_name(st)]
            if not theme and short:
                # a short break is made for 1 or 2 names: each goes up once and gets the whole stretch,
                # so a scrolling name moves as slowly as it can (and with 2 names, both are said)
                turns = min(slots, short[0])
            S["slot"] = float(S["slot"]) * slots / turns
            S["show_for"] = max(2.0, S["slot"] - 0.6)
            slots = turns
        cands = (eligible(everyone, theme) or everyone)[:min(int(S["max_names"]), slots)]
        if cands:
            # a name that needs longer to read than its turn (a long message scrolling on a small matrix):
            # fewer, longer turns in the same stretch of the break, instead of rushing it across
            need = max(need_secs(n.get("text", "")) for n in cands) + 0.4
            window = slots * float(S["slot"])
            if need > float(S["slot"]) + 0.05:
                fit = max(1, int(window // need))
                while fit < min(slots, len(cands)) and window / (fit + 1) >= 0.8 * need:
                    fit += 1                    # a little quicker than reading speed keeps one more kid in this break
                if fit < slots:
                    log("giving each name more time to be read: %d turn(s) of %.1fs instead of %d of %.1fs"
                        % (fit, window / fit, slots, float(S["slot"])))
                    S["slot"] = window / fit
                    S["show_for"] = max(2.0, S["slot"] - 0.6)
                    slots = fit
        names = cands[:slots]
        color = data.get("color") or color
        CUR.update(kind=("theme: %s" % THEMES[theme].get("name", theme)) if theme else "timed", note="")
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
    rows = {}
    try:
        return _show_turns(names, slots, color, rows)
    finally:
        out = []
        for item in names:
            r = rows.get(item.get("id")) or {}
            out.append({"text": item.get("text", ""), "shown": bool(r.get("shown")),
                        "voice": r.get("voice") or ("" if r.get("shown") else "waits for the next break")})
        break_report(CUR["kind"] or "timed", out, CUR.get("note", ""))


def _mark(rows, item, shown=None, said=False):
    r = rows.setdefault(item.get("id"), {})
    if shown:
        r["shown"] = True
    if said and r.get("voice") != "said":
        r["voice"] = SAY.get("last") or ""


CUR = {"kind": "", "note": ""}       # the break running now, for its report


def _show_turns(names, slots, color, rows):
    prefetch(names)
    done = []
    talk_until = 0
    voice_until = 0.0                                  # when the name being said right now ends
    outro_at = float(S["names_start"]) + slots * float(S["slot"])    # the break audio's outro starts about here
    if QUIET["until"]:
        # extra spots the break has room for are shown, but not said: Santa's own outro is talking by then
        outro_at = min(outro_at, QUIET["until"])
    QUIET["until"] = 0.0
    # A kid with a picture gets two turns: the picture (while the name is said), then the name.
    # Only whole kids that fit; the rest go up in the next break. Spare slots go round the names again.
    turns = []
    for item in names:
        need = 2 if picture(item) else 1
        if len(turns) + need > slots:
            break
        if turns and float(S["names_start"]) + len(turns) * float(S["slot"]) + 2.0 > outro_at:
            break                                    # a new kid isn't said over Santa: next break
        if need == 2:
            turns.append(("pic", item))
        turns.append(("name", item))
    first = len(turns)
    base = list(turns)
    i = 0
    while turns and len(turns) < slots:              # go round again, pictures too
        kind, item = base[i % len(base)]
        if kind == "pic" and (len(turns) + 2 > slots or
                              float(S["names_start"]) + len(turns) * float(S["slot"]) + 1.0 > outro_at):
            turns.append(("name", item))   # no room for its name after it, or Santa is talking: just the name
            i += 2                         # (and not the same name again right after)
            continue
        turns.append((kind, item))
        i += 1
    said = set()                                     # (turn, kid) where the name was said with the picture
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
                if PIC_ON or HELD:
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
        repeat = k >= first and item["id"] in done   # a kid whose first turn was skipped (running late) is said on the next one
        if kind == "pic":
            if PIC_ON or HELD:
                clear_pictures()                         # the last name or picture comes down first
            if show_picture(item):
                log("showing the picture of:", item["text"])
            elif show(item["text"], color):              # no screen took the picture (an older FPP): the name now instead
                log("showing:", item["text"])
            else:
                continue
            _mark(rows, item, shown=True)
            try:
                say_for = max(0.5, min(8.0, float(item.get("say_for") or 2.0)))
            except (TypeError, ValueError):
                say_for = 2.0
            # the name is said while the picture is up; going round again, only when Santa has finished
            # the last one and it ends before the outro (same as a name on its own)
            if not repeat or (S["say_every_time"] and time.time() >= voice_until and el + say_for <= outro_at - 0.3):
                if talk_until:
                    stop_talking()
                talk_until = say(item)
                voice_until = time.time() + say_for
                _mark(rows, item, said=True)
            said.add((k, item["id"]))
            if item["id"] not in done:
                done.append(item["id"])
            continue
        if HELD:
            clear_pictures(names_only=True)              # a name we kept on over a picture comes down first
        if show(item["text"], color):
            log("showing:", item["text"])
            _mark(rows, item, shown=True)
            try:
                say_for = max(0.5, min(8.0, float(item.get("say_for") or 2.0)))
            except (TypeError, ValueError):
                say_for = 2.0
            # a name going round again is only said when Santa has finished the last one and it ends before the outro
            if (k - 1, item["id"]) in said:
                pass                                     # just said with its picture
            elif repeat and (time.time() < voice_until or el + say_for > outro_at - 0.3):
                pass
            elif S["say_every_time"] or not repeat:
                if talk_until:
                    stop_talking()
                talk_until = say(item)
                voice_until = time.time() + say_for
                _mark(rows, item, said=True)
            if item["id"] not in done:
                done.append(item["id"])
        else:
            log("no screen took", item["text"], "- it will wait for the next break")
            rows.setdefault(item.get("id"), {}).setdefault("voice", "no screen took it")
    if PIC_ON or HELD:
        # the last one stays up for its whole turn, then comes down (FPP won't switch it off by itself)
        end_at = float(S["names_start"]) + len(turns) * float(S["slot"]) - 0.4
        t0 = time.time()
        while time.time() - t0 < float(S["slot"]) + 1:
            st = master_status()
            if not in_break(st):
                break
            el = elapsed(st)
            if el <= 0.05:
                el = time.time() - BRK["t0"] + 0.5
            if el >= end_at:
                break
            time.sleep(0.1)
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
            if show(item["text"], color):              # straight over the picture, no flicker
                log("showing (during the song):", item["text"])
                done.append(item["id"])
                time.sleep(S["show_for"] + 1.0)
                clear_pictures()                       # a name kept on over a picture comes down now
            else:
                clear_pictures()
                log("no screen took", item["text"], "- trying again shortly")
                break
    finally:
        S["show_for"] = keep
        if PIC_ON or HELD:
            clear_pictures()
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
            if ok and flow_ready(bst):
                add(True, "break", "names break with no fixed timing: each name is up for as long as it needs, then the closing")
            elif ok and bst.get("simple") and not d["break"].get("intro"):
                add(True, "break", "tip: build the names break audio again on your website and each name gets the time it needs (no fixed timing)")
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
