# php-components

This project is designed to put a JSX-like language inside of PHP.

## Why would you do this?

I work very heavily in both PHP server backends and JavaScript based frontends. Generally I use PHP as the base of a RESTful API server, and React as the base of a SPA webapp. However, there are some rare ocassions when I have the need to build a MPA (Multi Page App, so last decade, I know). In that case, I don't really want to give up the ability to write JSX code, as it's an extremely flexible way to rapidly build structured frontends.

Thus, this project. This allows the creation of frontend component classes in PHP and wrapping JSX code inside of those components.

## How it works

This library was inspired by React, so many of its functions are very React-like. You define components that extend a `Component` class and a `render` method. This method can return JSX code, but can also just print HTML like PHP normally does (this is how the low level components function). To build the page, you need to define a top level component and instruct the system to render it.

### Component

The `Component` class allows you to define a component that can be rendered to HTML.

| Function | Description | Returns |
|---|---|--|
| render | Called when the component is rendered | Can return JSX or nothing at all if you're directly printing HTML |

*Note:* The `Component` constructor is vital for making sure attributes are set properly. If you are overloading the constructor, make sure you call the parent constructor.

Any class extending `Component` has access to any attributes as well as the special attribute `children` as instance variables thanks to magic methods.

Example:

```
<?php

class CenteredContainer extends Component {
    function render() {
        return __JSX
            <div style="display: flex; justify-content: center;">
                {$this->children}
            </div>
        __JSX;
    }
}

?>
```

Note the use of __JSX wrapping the JSX code. These tags are required at start and end of every JSX block so the transpile can find it.

### Rendering the app

Rendering at top level is very similar to how React works. In order to render, simply call `JSXRenderer::renderComponent` on the component to be rendered.

Example:

```
<?php

$app = new App(); // must be an instance of Component

JSXRenderer::renderComponent($app)

?>
```

At this point the rendered HTML will be output to stdout.

### Autoloading

This module has its own autoloading system. This does not work with Composer's autoload system because I can't figure out how to make it work. The autoload sets up every class in the system. You can also pass in your build directory and any file there will also be added. Note that each class is expected to be inside a file with the same name. For example `App` should be in `App.php` in the build directory (meaning `App.phpx` in your source directory).

Example:

```
<?php

// as stated above, I don't have composer autoload working, so this just pulls in the right file
include_once(dirname(__FILE__) . "/../vendor/bucky24/php-components/src/PhpComponents/utils/autoLoad.php");

initAutoload(array(
    dirname(__FILE__) . "/build",
));

$app = new App();

JSXRenderer::renderComponent($app);

?>
```

### Compiling

In order to transpile the phpx files into php files, you must run the compiler, which right now does not work automatically. To run:

`php ./vendor/bucky24/php-components/src/PhpComponents/compiler.php --file <name of phpx file> --buildDir <the build dir to output the .php file to>`