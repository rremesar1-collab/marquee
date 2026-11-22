# Builds the English (Canada) and English (UK) spelling files from the .pot files.
# Run after adding new wording:  pip install polib && python3 tools/english-spelling.py
import re, sys
sys.path.insert(0, __import__('os').path.dirname(__file__))
import polib
# spelling only; Canada keeps -ize (organize), the UK/Australia use -ise
BOTH = [
    (r'colo(r)(s|ed|ing|ful)?', lambda m: 'colou' + m.group(1) + (m.group(2) or '')),
    (r'multicolo(r)', lambda m: 'multicolou' + m.group(1)),
    (r'recolo(r)ed', lambda m: 'recolou' + m.group(1) + 'ed'),
    (r'favo(r)ite(s)?', lambda m: 'favou' + m.group(1) + 'ite' + (m.group(2) or '')),
    (r'neighbo(r)(s|hood|hoods)?', lambda m: 'neighbou' + m.group(1) + (m.group(2) or '')),
    (r'cancel(ed|ing)', lambda m: 'cancell' + m.group(1)),
    (r'travel(ed|ing|er|ers)', lambda m: 'travell' + m.group(1)),
    (r'cent(er)(ed)?', lambda m: 'centr' + ('ed' if m.group(2) else 'e')),
]
UK = [
    (r'customiz(e|ed|es|ing)', lambda m: 'customis' + m.group(1)),
    (r'synchroniz(e|ed|es|ing)', lambda m: 'synchronis' + m.group(1)),
]
PHRASES = {
    'en_CA': [('“Town, State ZIP”', '“Town, Province postal code”'), ('ZIP / postcode', 'Postal code'), ('ZIP code', 'Postal code'), ('your Postal code', 'your postal code')],
    'en_GB': [('“Town, State ZIP”', '“Town, postcode”'), ('ZIP / postcode', 'Postcode'), ('ZIP code', 'Postcode'), ('your Postcode', 'your postcode')],
}

def keep_case(src, out):
    if src.isupper():
        return out.upper()
    if src[0].isupper():
        return out[0].upper() + out[1:]
    return out

def convert(text, loc):
    for a, b in PHRASES[loc]:
        text = text.replace(a, b)
    rules = BOTH + (UK if loc == 'en_GB' else [])
    for pat, fn in rules:
        rx = re.compile(r'(?<![\w\-/#=:])' + pat + r'(?![\w\-/=])', re.I)
        text = rx.sub(lambda m: keep_case(m.group(0), fn(re.fullmatch(pat, m.group(0).lower()))), text)
    return text

NAMES = {'en_CA': 'English (Canada)', 'en_GB': 'English (UK)'}
def build(pot_path, out_dir, domain):
    pot = polib.pofile(pot_path)
    for loc in ('en_CA', 'en_GB'):
        po = polib.POFile()
        po.metadata = {'Project-Id-Version': domain, 'Language': loc, 'MIME-Version': '1.0',
                       'Content-Type': 'text/plain; charset=UTF-8', 'Content-Transfer-Encoding': '8bit',
                       'Plural-Forms': 'nplurals=2; plural=(n != 1);'}
        for e in pot:
            if e.msgid_plural:
                a, b = convert(e.msgid, loc), convert(e.msgid_plural, loc)
                if a != e.msgid or b != e.msgid_plural:
                    po.append(polib.POEntry(msgid=e.msgid, msgid_plural=e.msgid_plural, msgctxt=e.msgctxt, msgstr_plural={0: a, 1: b}))
            else:
                a = convert(e.msgid, loc)
                if a != e.msgid:
                    po.append(polib.POEntry(msgid=e.msgid, msgctxt=e.msgctxt, msgstr=a))
        po.save(f'{out_dir}/{domain}-{loc}.po')
        po.save_as_mofile(f'{out_dir}/{domain}-{loc}.mo')
        print(domain, loc, len(po), out_dir.split('/marquee/')[-1])

if __name__ == '__main__':
    R = __import__('os').path.dirname(__import__('os').path.dirname(__import__('os').path.abspath(__file__))) + '/'
    build(R + 'plugin/marquee/languages/marquee.pot', R + 'plugin/marquee/languages', 'marquee')
    build(R + 'plugin/marquee/languages/ridgeroad.pot', R + 'plugin/marquee/languages', 'ridgeroad')
    build(R + 'theme/ridgeroad/languages/ridgeroad.pot', R + 'theme/ridgeroad/languages', 'ridgeroad')
