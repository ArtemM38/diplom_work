import zipfile
import xml.etree.ElementTree as ET
import sys

path = sys.argv[1]
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
with zipfile.ZipFile(path) as z:
    root = ET.fromstring(z.read("word/document.xml"))

paras = []
for p in root.iter(f"{W}p"):
    texts = []
    for t in p.iter(f"{W}t"):
        if t.text:
            texts.append(t.text)
        if t.tail:
            texts.append(t.tail)
    line = "".join(texts).strip()
    if line:
        paras.append(line)

out = sys.argv[2] if len(sys.argv) > 2 else None
text = "\n".join(paras)
if out:
    with open(out, "w", encoding="utf-8") as f:
        f.write(text)
else:
    sys.stdout.reconfigure(encoding="utf-8")
    print(text)
