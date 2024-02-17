const serve = require("@bucky24/node-php");
const path = require("path");

serve(path.resolve(__dirname, "examples", "build"), 90);