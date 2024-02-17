# php-components

This project is designed to put a JSX-like language inside of PHP

## Why would you do this?

I work very heavily in both PHP server backends and JavaScript based frontends. Generally I use PHP as the base of a RESTful API server, and React as the base of a SPA webapp. However, there are some rare occasions when I have the need to build a MPA (Multi Page App, so last decade, I know). In that case, I don't really want to give up the ability to write JSX-like code, as it's an extremely flexible way to rapidly build structured frontends.

Thus, this project. This allows the creation of frontend component classes in PHP and wrapping JSX code inside of those components.

## How it works

This library was inspired by React, so many of its functions are very React-like. You define components as functions. These functions can take in some props, as an array, and should contain HTML directly.

Any tag that has capital letters in it is assumed to be a Component, and will be transpiled accordingly.

### Components

Components are functions that take in a $params. Like React, `children` will be a key in $params.

Example:

```
//CenteredComponent.phpx:

<?php

function CenteredContainer($params) { ?>
    <div style="display: flex; justify-content: center;">
        <?php params['children']; ?>
    </div>
<?php }

?>

// App.phpx

<?php

function App($params) { ?>
    <CenteredComponent>
        This will be centered text
    </CenteredComponent>
<?php }
?>
```

### Rendering the app

Rendering at top level requires an index.phpx file. This file is somewhat special, as the compiler will inject all the requires needed to actually load all components into it.

```
<div class="outerApp">
    <App />
</div>
```

### Compiling

In order to transpile the phpx files into php files, you must run the compiler, which right now does not work automatically. To run:

`php ./vendor/bucky24/php-components/src/PhpComponents/compiler.php --dir <directory of the code> --buildDir <the build dir to output the .php file to>`

You can also compile files directly:

`php ./vendor/bucky24/php-components/src/PhpComponents/compiler.php --file <name of phpx file> --buildDir <the build dir to output the .php file to>`

### Other Special Files

If an `index.html` is found in the build directory, the compiler will use that as a template for the resulting `index.php`.

Any `.css` or `.js` files found in the directory will be copied to the build directory.