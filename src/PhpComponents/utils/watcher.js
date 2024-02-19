const fs = require("fs");
const path = require("path");
const { exec } = require("child_process");

const dir = process.argv[2];

let fullDir = dir;
if (dir.startsWith(".")) {
    fullDir = path.resolve(dir);
}

const compilerPath = path.resolve(__dirname, "..", "compiler.php");

console.log("Watching", dir, "for changes");
fs.watch(dir, { recursive: true }, (event, file) => {
    const fullFile = path.join(fullDir, file);
    //console.log(event, fullFile);

    const command = `php ${compilerPath} --dir ${fullDir}`;

    exec(command, (err, stdout, stderr) => {
        console.log(stdout);
    });
});