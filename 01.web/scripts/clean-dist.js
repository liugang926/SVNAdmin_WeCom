const fs = require("fs");
const path = require("path");

const distDir = path.resolve(__dirname, "..", "dist");

function removeEntry(entryPath) {
  if (!fs.existsSync(entryPath)) {
    return;
  }

  const stat = fs.lstatSync(entryPath);
  if (stat.isDirectory() && !stat.isSymbolicLink()) {
    for (const name of fs.readdirSync(entryPath)) {
      removeEntry(path.join(entryPath, name));
    }
    fs.rmdirSync(entryPath);
    return;
  }

  fs.unlinkSync(entryPath);
}

removeEntry(distDir);
fs.mkdirSync(distDir, { recursive: true });
