const fs = require("fs");
const path = require("path");
const { exec } = require("child_process");

if (process.argv.length <= 2) {
    console.error("USAGE: watcher.js <directory>");
    process.exit(1);
}

const dir = process.argv[2];

let fullDir = dir;
if (dir.startsWith(".")) {
    fullDir = path.resolve(dir);
}

const compilerPath = path.resolve(__dirname, "..", "compiler.php");

function doCompile() {
    const command = `php ${compilerPath} --dir ${fullDir}`;

    exec(command, (err, stdout, stderr) => {
        console.log(stdout);
    });
}

console.log("Watching", dir, "for changes");
fs.watch(dir, { recursive: true }, (event, file) => {
    doCompile();
});

// run one right at the start to get us going
doCompile();