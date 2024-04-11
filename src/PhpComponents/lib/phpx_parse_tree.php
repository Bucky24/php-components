<?php

include_once(__DIR__ . "/logger.php");

function getStackData($tag) {
    if ($tag['type'] === 'tag') {
        return array(
            "type" => $tag['type'],
            "tag" => $tag['tag'],
            "selfClosing" => $tag['selfClosing'],
            "endTag" => $tag['endTag'],
            "children" => array(),
            "originalData" => $tag,
        );
    } else {
        return $tag;
    }
}

function buildTree($tags) {
    $tag_stack = array();
    $tree = array();

    foreach ($tags as $tag) {
        // ignore only whitespace
        if ($tag['type'] === 'text' && preg_replace('/\s+/', '', $tag['text']) === "") {
            continue;
        }

        $top_of_stack = null;
        $stack_count = count($tag_stack)-1;
        if (count($tag_stack) > 0) $top_of_stack = $tag_stack[$stack_count];

        $top_log = $top_of_stack === null ? 'NONE' : $top_of_stack['tag'];
        $count_of_stack = count($tag_stack);
        $tag_log = $tag['type'] === 'tag' ? "Tag {$tag['tag']} end: " . var_export($tag['endTag'], true) : $tag['type'];

        debugLog("$top_log => $tag_log on stack is $count_of_stack elems");

        $immediate_push = $tag['type'] !== 'tag' || $tag['selfClosing'] || $tag['phpTag'];

        // if there is no top, then no stack
        if (!$top_of_stack) {
            if ($immediate_push) {
                // self closing or non tag goes right on the tree
                $tree[] = getStackData($tag);
            } else {
                debugLog("pushing onto stack as first element");
                // otherwise push it onto the stack as the first element
                $tag_stack[] = getStackData($tag);
            }
            continue;
        }

        // if the tag doesn't match or is not a tag at all
        if ($tag['type'] !== 'tag' || $tag['tag'] !== $top_of_stack['tag']) {
            if ($immediate_push) {
                // self closing or non-tag becomes a child of the top
                $tag_stack[$stack_count]['children'][] = getStackData($tag);
            } else {
                debugLog("Pushing to stack as new entry");
                // otherwise it's a new entry on the stack
                $tag_stack[] = getStackData($tag);
            }
        } else if ($tag['endTag']) {
            // if end tag and tag matches, then pop as a child of the new top
            $old_top = array_pop($tag_stack);

            debugLog("Popping tag");
            
            if (count($tag_stack) > 0) {
                debugLog("Pushing to stack as child of previous entry");
                $tag_stack[count($tag_stack)-1]['children'][] = $old_top;
            } else {
                debugLog("Pushing to tree");
                // if there is nothing else, push onto the tree
                $tree[] = $old_top;
            }
        } else {
            // in this case we also have to push 
            if ($immediate_push) {
                debugLog("Pushing to stack as child");
                // self closing or non-tag becomes a child of the top
                $tag_stack[$stack_count]['children'][] = getStackData($tag);
            } else {
                debugLog("Pushing to stack");
                // otherwise it's a new entry on the stack
                $tag_stack[] = getStackData($tag);
            }
        }
    }

    debugLog_r($tree);
    return $tree;
}

?>