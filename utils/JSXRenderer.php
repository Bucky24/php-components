<?php

class JSXRenderer {
    static function renderComponent($component) {
        JSXRenderer::doRender($component);
    }

    private static function doRender($component) {
        if ($component instanceof Component) {
            $childComponents = $component->render();
            if ($childComponents !== null) {
                foreach ($childComponents as $child) {
                    JSXRenderer::doRender($child);
                }
            }
        } else if ($component instanceof JSXElement) {
            $component->preRender();

            $children = $component->getChildren();
            foreach ($children as $child) {
                JSXRenderer::doRender($child);
            }

            $component->postRender();
        } else if ($component instanceof JSXText) {
            $component->render();
        }
    }
}

?>