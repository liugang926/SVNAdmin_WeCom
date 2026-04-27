const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const distDir = path.join(root, "dist");
const logoPath = path.join(root, "src", "assets", "images", "logo.png");
const faviconPath = path.join(distDir, "favicon.ico");
const wellKnownDir = path.join(distDir, ".well-known", "appspecific");
const chromeDevtoolsPath = path.join(wellKnownDir, "com.chrome.devtools.json");
const fallbackPng = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgAacVZ8AAAAASUVORK5CYII=",
  "base64"
);

function getPngSize(buffer) {
  const isPng =
    buffer.length > 24 &&
    buffer.readUInt32BE(0) === 0x89504e47 &&
    buffer.readUInt32BE(4) === 0x0d0a1a0a &&
    buffer.toString("ascii", 12, 16) === "IHDR";

  if (!isPng) {
    return { width: 1, height: 1 };
  }

  return {
    width: buffer.readUInt32BE(16),
    height: buffer.readUInt32BE(20),
  };
}

function toIcoByte(size) {
  return size >= 256 ? 0 : size;
}

function createIcoFromPng(png) {
  const { width, height } = getPngSize(png);
  const header = Buffer.alloc(22);

  header.writeUInt16LE(0, 0);
  header.writeUInt16LE(1, 2);
  header.writeUInt16LE(1, 4);
  header.writeUInt8(toIcoByte(width), 6);
  header.writeUInt8(toIcoByte(height), 7);
  header.writeUInt8(0, 8);
  header.writeUInt8(0, 9);
  header.writeUInt16LE(1, 10);
  header.writeUInt16LE(32, 12);
  header.writeUInt32LE(png.length, 14);
  header.writeUInt32LE(header.length, 18);

  return Buffer.concat([header, png]);
}

if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}

const logo = fs.existsSync(logoPath) ? fs.readFileSync(logoPath) : fallbackPng;
fs.writeFileSync(faviconPath, createIcoFromPng(logo));

fs.mkdirSync(wellKnownDir, { recursive: true });
fs.writeFileSync(chromeDevtoolsPath, "{}\n");
