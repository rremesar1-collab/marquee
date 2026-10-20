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
    how its setup check went (Marquee -> Settings -> Your Name in Lights).

Your website never connects into your house: this script reaches out to it,
so no router ports need to be opened.

Settings live in marquee_names.json next to this script. Download that file
from your website (Marquee -> Settings -> Your Name in Lights), or make it here with:
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
import urllib.request

VERSION = "3.3"
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
LOCK = "/tmp/marquee_names.pid"
LOGFILE = "/home/fpp/media/logs/marquee_names.log"
MUSIC = os.environ.get("MARQUEE_MUSIC", "/home/fpp/media/music")   # spoken names are saved here (FPP plays media from this folder)
VOICE_PREFIX = "marquee-voice-"
VOICE_KEEP = 300                                                    # newest clips kept on the FPP
UA = "Mozilla/5.0 (X11; Linux aarch64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 FPP-Marquee/" + VERSION

DEFAULTS = {
    "site": "",                       # your website, e.g. https://yourshow.com/
    "key": "",                        # private key from Marquee -> Settings -> Your Name in Lights
    "break_playlist": "Names in Lights",
    "screens": [],                    # [{"name": "matrix", "fpp": "http://192.168.1.50", "model": "MatrixText"}]
    "names_start": 4.0,               # seconds into the break when the first name appears
    "slot": 3.75,                     # seconds per name
    "show_for": 3,                    # seconds each name stays up
    "max_names": 6,                   # names per break; the rest wait for the next one
    "max_width": 0.62,                # text stays inside this part of the screen (fraction of width)
    "max_height": 0.42,               # ... and of height
    "font": "",                       # "" picks a bold font automatically
    "check_every": 20,                # seconds between website checks during songs
    "voice": True,                    # play spoken names when the website sends them (false = never)
    "voice_volume": 0,                # -100..100, added to FPP's volume for the spoken names
    "voice_slot": 2,                  # FPP media stream slot for the names (slot 1 is the break's own music)
    "short_breaks": True,             # 1 or 2 names: use "<break playlist> 1" / "<break playlist> 2" if they exist
    "say_every_time": True,           # with fewer names than slots the screens repeat them: say them again each time
    "talk_effect": "",                # FPP effect (.eseq) that moves the singing faces while a name is spoken
}
S = dict(DEFAULTS)


def log(*a):
    line = time.strftime("%Y-%m-%d %H:%M:%S ") + " ".join(str(x) for x in a)
    print(line, flush=True)
    try:
        if os.path.exists(LOGFILE) and os.path.getsize(LOGFILE) > 500000:
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
                "(Marquee -> Settings -> Your Name in Lights) and upload it in FPP -> File Manager "
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
    screens = []
    for i, sc in enumerate(S["screens"] or []):
        if isinstance(sc, dict) and sc.get("fpp") and sc.get("model"):
            host = sc["fpp"].strip()
            if not host.startswith("http"):
                host = "http://" + host
            screens.append({"name": (sc.get("name") or "screen %d" % (i + 1)).strip(), "fpp": host.rstrip("/"), "model": sc["model"].strip()})
    S["screens"] = screens
    st = break_state()
    if st.get("start") and st.get("slot"):     # timing of the break audio built on the website
        S["names_start"] = float(st["start"])
        S["slot"] = float(st["slot"])
        S["show_for"] = max(2.0, float(st["slot"]) - 0.6)
    if not screens:
        problems.append("no screens in the settings file (each needs an FPP address and an overlay model name)")
    return problems


# ---------------------------------------------------------------- HTTP ----
def http_json(url, data=None, timeout=15):
    if data is not None:
        data = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(url, data=data, headers={"User-Agent": UA, "Accept": "application/json, text/plain, */*"})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        body = r.read().decode("utf-8", "replace")
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
    url = str(S["site"]).rstrip("/") + "/wp-admin/admin-ajax.php?action=rr_names_pull&key=" + urllib.parse.quote(str(S["key"]))
    data = {"done": ",".join(str(i) for i in done_ids)} if done_ids else {"ping": "1"}
    if st is not None:
        data["fpp"] = json.dumps({"status": st.get("status_name", ""), "playlist": playlist_name(st),
                                  "sequence": st.get("current_sequence", "")})
    if report is not None:
        data["report"] = json.dumps(report)
    res = http_json(url, data)
    if not res or not res.get("success"):
        raise RuntimeError("website said: %s" % (res,))
    if isinstance(res["data"], dict) and res["data"].get("break"):
        try:
            sync_break(res["data"]["break"])
        except Exception as e:
            log("could not set up the names break from the website:", e)
    return res["data"]


# --------------------------------------------------- break from website ----
STATE = os.path.join(HERE, "marquee_break_state.json")
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
    if not isinstance(b, dict) or not b.get("v") or not b.get("full"):
        return
    st = break_state()
    if str(st.get("v")) == str(b["v"]) or in_break(master_status()):
        return
    for k, name in BREAK_FILES.items():
        if not b.get(k):
            return
        download(b[k], os.path.join(MUSIC, name))
    bp = S["break_playlist"]
    base = fpp_get(MASTER, "/api/playlist/" + urllib.parse.quote(bp)) or {}
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
        body = json.dumps(new).encode()
        req = urllib.request.Request(MASTER.rstrip("/") + "/api/playlist/" + urllib.parse.quote(pl), data=body,
                                     headers={"Content-Type": "application/json"})
        urllib.request.urlopen(req, timeout=10).read()
    st.update({"v": str(b["v"]), "start": float(b.get("start") or S["names_start"]), "slot": float(b.get("slot") or S["slot"]),
               "seq": entry.get("sequenceName"), "t": time.strftime("%Y-%m-%d %H:%M")})
    with open(STATE, "w") as f:
        json.dump(st, f, indent=1)
    S["names_start"], S["slot"] = st["start"], st["slot"]
    S["show_for"] = max(2.0, st["slot"] - 0.6)
    _playlists["t"] = 0
    log("names break updated from the website: '%s', '%s 2' and '%s 1' now play the new audio "
        "(names start at %ss, one every %ss)" % (bp, bp, bp, st["start"], st["slot"]))


# -------------------------------------------------------------- master ----
def master_status():
    try:
        return fpp_get(MASTER, "/api/fppd/status") or {}
    except Exception as e:
        log("could not read FPP status:", e)
        return {}


def playlist_name(st):
    cp = st.get("current_playlist") or {}
    return cp.get("playlist", "") if isinstance(cp, dict) else ""


def in_break(st):
    if st.get("status_name") != "playing":
        return False
    pl = playlist_name(st)
    if pl == S["break_playlist"] or pl in short_playlists().values():
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


def fpp_playlists():
    """Playlist names on this FPP (cached for a minute)."""
    if time.time() - _playlists["t"] > 60:
        try:
            _playlists["names"] = fpp_get(MASTER, "/api/playlists") or []
            _playlists["t"] = time.time()
        except Exception:
            pass
    return _playlists["names"]


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


def queue_break(waiting=99):
    # "ifNotRunning" = true so a second request never stacks a second break
    pl = short_playlists().get(waiting, S["break_playlist"])
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
        m = fpp_get(sc["fpp"], "/api/overlays/model/" + urllib.parse.quote(sc["model"]))
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


def show(text, color):
    """Put one name up on every screen; returns how many screens took it."""
    ok = 0
    for sc in S["screens"]:
        try:
            w, h, font = screen_info(sc)
            size, lines = layout(text, w, h)
            args = [sc["model"], "Transparent", "Text", color, font, size, "true", "Center", 0, S["show_for"],
                    "\\n".join(lines)]
            fpp_command(sc["fpp"], "Overlay Model Effect", args)
            ok += 1
        except Exception as e:
            log("could not show on", sc["name"], "(", sc["fpp"], ") -", e)
            _info.pop(sc["fpp"] + "|" + sc["model"], None)
    return ok


# --------------------------------------------------------------- break ----
def run_break(color):
    """Called when the break starts: fetch names, show them on the break's clock."""
    try:
        data = pull([])
        slots = slots_for(master_status())
        names = (data.get("names") or [])[:min(int(S["max_names"]), slots)]
        color = data.get("color") or color
    except Exception as e:
        log("break started but the website could not be reached:", e)
        return []
    log("break started with %d name(s), %d slot(s)" % (len(names), slots))
    prefetch(names)
    done = []
    talk_until = 0
    # Fill every slot: with fewer names than slots, cycle through them.
    for k in range(slots if names else 0):
        item = names[k % len(names)]
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
                return done
            if elapsed(st) >= at:
                break
            time.sleep(0.1)
        if show(item["text"], color):
            log("showing:", item["text"])
            if S["say_every_time"] or k < len(names):
                if talk_until:
                    stop_talking()
                talk_until = say(item)
            if item["id"] not in done:
                done.append(item["id"])
        else:
            log("no screen took", item["text"], "- it will wait for the next break")
    while in_break(master_status()):       # let the break finish
        if talk_until and time.time() >= talk_until:
            stop_talking()
            talk_until = 0
        time.sleep(0.2)
    if talk_until:
        stop_talking()
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
    st = master_status()
    if st:
        add(True, "fpp", "FPP status: %s" % (st.get("status_name") or "?"))
    else:
        add(False, "fpp", "could not read this FPP's status")
    try:
        pls = fpp_get(MASTER, "/api/playlists") or []
        if S["break_playlist"] in pls:
            short = [p for p in pls if p in ("%s 1" % S["break_playlist"], "%s 2" % S["break_playlist"])]
            add(True, "playlist", "playlist '%s' found%s" % (S["break_playlist"], (" (short breaks: %s)" % ", ".join(short)) if short else ""))
        else:
            add(False, "playlist", "no playlist named '%s' on this FPP (found: %s)" % (S["break_playlist"], ", ".join(pls) or "none"))
    except Exception as e:
        add(False, "playlist", "could not list playlists: %s" % e)
    if S["voice"] and site_voice:
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
        except Exception as e:
            add(False, "screen", "%s (%s): %s" % (sc["name"], sc["fpp"], e))
    return out, site_ok


def send_report(checks):
    try:
        pull([], None, {"v": VERSION, "checks": checks})
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
    key = ask("Private key (Marquee -> Settings -> Your Name in Lights)", cur["key"])
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
def single_instance():
    try:
        if os.path.exists(LOCK):
            old = int(open(LOCK).read().strip() or 0)
            if old and os.path.exists("/proc/%d" % old):
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
        show(sys.argv[2].upper(), "#FFFFFF")
        return
    single_instance()
    problems = load_settings()
    while problems and not (S["site"] and S["key"]):
        for p in problems:
            log("setup problem:", p)
        time.sleep(60)                        # wait for the settings file, check again in a minute
        problems = load_settings()
    log("started v%s; break playlist '%s'; screens: %s" % (
        VERSION, S["break_playlist"], ", ".join("%s=%s on %s" % (s["name"], s["model"], s["fpp"]) for s in S["screens"]) or "none"))
    checks, site_ok = run_checks(problems)
    for c in checks:
        log(("ok: " if c["ok"] else "PROBLEM: ") + c["msg"])
    if site_ok:
        send_report(checks)
    done = []
    queued = False
    last_check = 0.0
    color = "#FFFFFF"
    every = float(S["check_every"])
    while True:
        try:
            st = master_status()
            if in_break(st):
                done += run_break(color)
                queued = False
                pull(done)            # report what was shown
                done = []
                last_check = time.time()
                continue
            if st.get("status_name") != "playing":
                queued = False
                if done or time.time() - last_check >= 60:      # idle: check in once a minute
                    last_check = time.time()
                    pull(done, st)
                    done = []
                time.sleep(5)
                continue
            if time.time() - last_check >= every:
                last_check = time.time()
                data = pull(done, st)
                done = []
                color = data.get("color") or color
                waiting = len(data.get("names") or [])
                if waiting:
                    prefetch(data.get("names") or [])     # spoken names are ready before the break
                if waiting and not queued:
                    pl = queue_break(waiting)
                    queued = True
                    log("%d name(s) waiting - '%s' queued after the current song" % (waiting, pl))
            time.sleep(1)
        except Exception as e:
            log("problem:", e, "- trying again shortly")
            time.sleep(every)


if __name__ == "__main__":
    main()
