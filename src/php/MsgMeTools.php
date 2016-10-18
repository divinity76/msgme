<?php
declare(strict_types = 1);

namespace MsgMe\tools;

function isItSafeToOnlyUseFirstFormInputMatch(\DOMDocument $domd, \DOMNode $form = NULL): bool {
	$forms = getDOMDocumentFormInputs ( $domd, false );
	foreach ( $forms as $key => $form ) {
		if (count ( $form ) !== 1) {
			return false;
		}
		foreach ( $form [0] as $key2 => $inputs ) {
			if (count ( $inputs ) !== 1) {
				return false;
			}
		}
	}
	return true;
}

function return_var_dump(/*...*/){
	$args = func_get_args ();
	ob_start ();
	call_user_func_array ( 'var_dump', $args );
	return ob_get_clean ();
}
function getDOMDocumentFormInputs(\DOMDocument $domd, bool $getOnlyFirstMatches = false): array {
	// :DOMNodeList?
	$forms = $domd->getElementsByTagName ( 'form' );
	$parsedForms = array ();
	$isDescendantOf = function (\DOMNode $decendant, \DOMNode $ele): bool {
		$parent = $decendant;
		while ( NULL !== ($parent = $parent->parentNode) ) {
			if ($parent === $ele) {
				return true;
			}
		}
		return false;
	};
	// i can't use array_merge on DOMNodeLists :(
	$merged = function () use (&$domd): array {
		$ret = array ();
		foreach ( $domd->getElementsByTagName ( "input" ) as $input ) {
			$ret [] = $input;
		}
		foreach ( $domd->getElementsByTagName ( "textarea" ) as $textarea ) {
			$ret [] = $textarea;
		}
		return $ret;
	};
	$merged = $merged ();
	foreach ( $forms as $form ) {
		$inputs = function () use (&$domd, &$form, &$isDescendantOf, &$merged): array {
			$ret = array ();

			foreach ( $merged as $input ) {
				// hhb_var_dump ( $input->getAttribute ( "name" ), $input->getAttribute ( "id" ) );
				if ($input->hasAttribute ( "disabled" )) {
					// ignore disabled elements?
					continue;
				}
				$name = $input->getAttribute ( "name" );
				if ($name === '') {
					// echo "inputs with no name are ignored when submitted by mainstream browsers (presumably because of specs)... follow suite?", PHP_EOL;
					continue;
				}
				if (! $isDescendantOf ( $input, $form ) && $form->getAttribute ( "id" ) !== '' && $input->getAttribute ( "form" ) !== $form->getAttribute ( "id" )) {
					// echo "this input does not belong to this form.", PHP_EOL;
					continue;
				}
				if (! array_key_exists ( $name, $ret )) {
					$ret [$name] = array (
							$input
					);
				} else {
					$ret [$name] [] = $input;
				}
			}
			return $ret;
		};
		$inputs = $inputs (); // sorry about that, Eclipse gets unstable on IIFE syntax.
		$hasName = true;
		$name = $form->getAttribute ( "id" );
		if ($name === '') {
			$name = $form->getAttribute ( "name" );
			if ($name === '') {
				$hasName = false;
			}
		}
		if (! $hasName) {
			$parsedForms [] = array (
					$inputs
			);
		} else {
			if (! array_key_exists ( $name, $parsedForms )) {
				$parsedForms [$name] = array (
						$inputs
				);
			} else {
				$parsedForms [$name] [] = $tmp;
			}
		}
	}
	unset ( $form, $tmp, $hasName, $name, $i, $input );
	if ($getOnlyFirstMatches) {
		foreach ( $parsedForms as $key => $val ) {
			$parsedForms [$key] = $val [0];
		}
		unset ( $key, $val );
		foreach ( $parsedForms as $key1 => $val1 ) {
			foreach ( $val1 as $key2 => $val2 ) {
				$parsedForms [$key1] [$key2] = $val2 [0];
			}
		}
	}
	return $parsedForms;
}