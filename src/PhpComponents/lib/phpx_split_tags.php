<?php

function generateTags($content) {
    $tags = array();
    $inTag = false;
    $inPhpTag = false;
    $tagBuffer = "";
    for ($i=0;$i<strlen($content);$i+=1) {
        $char = substr($content, $i, 1);
        if ($char === '<' && !$inTag) {
            if (strlen($tagBuffer) > 0) {
                //print "Text! $tagBuffer\n";
                $tags[] = array(
                    "type" => "text",
                    "text" => $tagBuffer,
                );
            }
            $inTag = true;
            $tagBuffer = "";
        }

        //if ($inTag) {
            $tagBuffer .= $char;
            //print $char;
        //}

        if ($char === "?") {
            debugLog("Found a question mark! buffer is $tagBuffer");
            if ($tagBuffer === "<?") {
                $inPhpTag = true;
            }
        }

        if ($char === '>') {
            debugLog("We found a >. Tag is $tagBuffer. Are we already inside php tag? " . var_export($inPhpTag, true));
            $lastChar = substr($tagBuffer, strlen($tagBuffer)-2, 1);
            $selfClosing = $lastChar === '/';
            $endTag = substr($tagBuffer, 1, 1) === '/';
            $phpTag = substr($tagBuffer, 1, 1) === '?';

            if ($inPhpTag && $lastChar !== "?") {
                // in this case we just got a > for some non-tag related reason. You can ignore it.
                continue;
            }

            $end = strlen($tagBuffer)-2;
            $start = 1;
            if ($selfClosing || $phpTag) {
                $end -= 1;
            }
            if ($endTag) {
                $start += 1;
                $end -= 1;
            }
            $tagData = substr($tagBuffer, $start, $end);

            $tagDataList = preg_split('/\s+/', $tagData);
            $tag = $tagDataList[0];

            //print $tagData . "\n";
            
            $tags[] = array(
                "type" => "tag",
                "selfClosing" => $selfClosing,
                "endTag" => $endTag,
                "phpTag" => $phpTag,
                "contents" => $tagData,
                "tag" => $tag,
            );

            $tagBuffer = "";
            $inTag = false;
            $inPhpTag = false;
        }
    }

    return $tags;
}

?>