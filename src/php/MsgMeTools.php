<?php

declare(strict_types=1);

namespace MsgMe\tools;

function isItSafeToOnlyUseFirstFormInputMatch(\DOMDocument $domd, \DOMNode $form = NULL): bool
{
    $forms = getDOMDocumentFormInputs($domd, false);
    foreach ($forms as $key => $form) {
        if (count($form) !== 1) {
            return false;
        }
        foreach ($form[0] as $key2 => $inputs) {
            if (count($inputs) !== 1) {
                return false;
            }
        }
    }
    return true;
}

function return_var_dump(/*...*/)
{
    $args = func_get_args();
    ob_start();
    call_user_func_array('var_dump', $args);
    return ob_get_clean();
}

function getDOMDocumentFormInputs(\DOMDocument $domd, bool $getOnlyFirstMatches = false, bool $getElements = true): array
{
    // :DOMNodeList?
    if (!$getOnlyFirstMatches && !$getElements) {
        throw new \InvalidArgumentException('!$getElements is currently only implemented for $getOnlyFirstMatches (cus im lazy and nobody has written the code yet)');
    }
    $forms = $domd->getElementsByTagName('form');
    $parsedForms = array();
    $isDescendantOf = function (\DOMNode $decendant, \DOMNode $ele): bool {
        $parent = $decendant;
        while (NULL !== ($parent = $parent->parentNode)) {
            if ($parent === $ele) {
                return true;
            }
        }
        return false;
    };
    // i can't use array_merge on DOMNodeLists :(
    $merged = function () use (&$domd): array {
        $ret = array();
        foreach ($domd->getElementsByTagName("input") as $input) {
            $ret[] = $input;
        }
        foreach ($domd->getElementsByTagName("textarea") as $textarea) {
            $ret[] = $textarea;
        }
        foreach ($domd->getElementsByTagName("button") as $button) {
            $ret[] = $button;
        }
        return $ret;
    };
    $merged = $merged();
    foreach ($forms as $form) {
        $inputs = function () use (&$domd, &$form, &$isDescendantOf, &$merged): array {
            $ret = array();
            foreach ($merged as $input) {
                // hhb_var_dump ( $input->getAttribute ( "name" ), $input->getAttribute ( "id" ) );
                if ($input->hasAttribute("disabled")) {
                    // ignore disabled elements?
                    continue;
                }
                $name = $input->getAttribute("name");
                if ($name === '') {
                    // echo "inputs with no name are ignored when submitted by mainstream browsers (presumably because of specs)... follow suite?", PHP_EOL;
                    continue;
                }
                if (!$isDescendantOf($input, $form) && $form->getAttribute("id") !== '' && $input->getAttribute("form") !== $form->getAttribute("id")) {
                    // echo "this input does not belong to this form.", PHP_EOL;
                    continue;
                }
                if (!array_key_exists($name, $ret)) {
                    $ret[$name] = array(
                        $input
                    );
                } else {
                    $ret[$name][] = $input;
                }
            }
            return $ret;
        };
        $inputs = $inputs(); // sorry about that, Eclipse gets unstable on IIFE syntax.
        $hasName = true;
        $name = $form->getAttribute("id");
        if ($name === '') {
            $name = $form->getAttribute("name");
            if ($name === '') {
                $hasName = false;
            }
        }
        if (!$hasName) {
            $parsedForms[] = array(
                $inputs
            );
        } else {
            if (!array_key_exists($name, $parsedForms)) {
                $parsedForms[$name] = array(
                    $inputs
                );
            } else {
                $parsedForms[$name][] = $tmp;
            }
        }
    }
    unset($form, $tmp, $hasName, $name, $i, $input);
    if ($getOnlyFirstMatches) {
        foreach ($parsedForms as $key => $val) {
            $parsedForms[$key] = $val[0];
        }
        unset($key, $val);
        foreach ($parsedForms as $key1 => $val1) {
            foreach ($val1 as $key2 => $val2) {
                $parsedForms[$key1][$key2] = $val2[0];
            }
        }
    }
    if ($getElements) {
        return $parsedForms;
    }
    $ret = array();
    foreach ($parsedForms as $formName => $arr) {
        $ret[$formName] = array();
        foreach ($arr as $ele) {
            $ret[$formName][$ele->getAttribute("name")] = $ele->getAttribute("value");
        }
    }
    return $ret;
}

function loadHTML_noemptywhitespace(string $html, int $extra_flags = 0, int $exclude_flags = 0): \DOMDocument
{
    $flags = LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS | LIBXML_NONET;
    $flags = ($flags | $extra_flags) & ~$exclude_flags;

    $domd = new \DOMDocument();
    $domd->preserveWhiteSpace = false;
    @$domd->loadHTML('<?xml encoding="UTF-8">' . $html, $flags);
    $removeAnnoyingWhitespaceTextNodes = function (\DOMNode $node) use (&$removeAnnoyingWhitespaceTextNodes): void {
        if ($node->hasChildNodes()) {
            // Warning: it's important to do it backwards; if you do it forwards, the index for DOMNodeList might become invalidated;
            // that's why i don't use foreach() - don't change it (unless you know what you're doing, ofc)
            for ($i = $node->childNodes->length - 1; $i >= 0; --$i) {
                $removeAnnoyingWhitespaceTextNodes($node->childNodes->item($i));
            }
        }
        if ($node->nodeType === XML_TEXT_NODE && !$node->hasChildNodes() && !$node->hasAttributes() && !strlen(trim($node->textContent))) {
            // echo "Removing annoying POS";
            // var_dump($node);
            $node->parentNode->removeChild($node);
        } // elseif ($node instanceof DOMText) { echo "not removed"; var_dump($node, $node->hasChildNodes(), $node->hasAttributes(), trim($node->textContent)); }
    };
    $removeAnnoyingWhitespaceTextNodes($domd);
    return $domd;
}
function loadHTML(string $html, int $extra_flags = 0, int $exclude_flags = 0): \DOMDocument
{
    $flags = LIBXML_HTML_NODEFDTD | LIBXML_NONET;
    $flags = ($flags | $extra_flags) & ~$exclude_flags;

    $domd = new \DOMDocument();
    //$domd->preserveWhiteSpace = false;
    @$domd->loadHTML('<?xml encoding="UTF-8">' . $html, $flags);
    return $domd;
}


function prettify_html(string $html): string
{
    $domd = loadHTML_noemptywhitespace($html);
    $domd->preserveWhiteSpace = false;
    $domd->formatOutput = true;
    return $domd->saveHTML();
}

function dd(...$args): void /*: never */
{
    $trace = debug_backtrace(0, 2)[1];
    echo "<pre>\ndd() called from ", $trace['file'], ":", $trace['line'], "\n";
    var_dump(...$args);
    echo "</pre>\n";
    die(1);
}
