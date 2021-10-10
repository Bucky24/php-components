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

Any class extending `Component` 