<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Domain;
// Generated draft D-054 grammar; not Protocol Freeze.
final class LanguageGrammar
{
    public const PATTERN = '(?:(?:[a-z]{2,3}(?:-[a-z]{3}){0,3}|[a-z]{4}|[a-z]{5,8})(?:-[a-z]{4})?(?:-(?:[a-z]{2}|[0-9]{3}))?(?:-(?:[a-z0-9]{5,8}|[0-9][a-z0-9]{3}))*(?:-[0-9a-wy-z](?:-[a-z0-9]{2,8})+)*(?:-x(?:-[a-z0-9]{1,8})+)?|x(?:-[a-z0-9]{1,8})+|art-lojban|cel-gaulish|en-gb-oed|i-ami|i-bnn|i-default|i-enochian|i-hak|i-klingon|i-lux|i-mingo|i-navajo|i-pwn|i-tao|i-tay|i-tsu|no-bok|no-nyn|sgn-be-fr|sgn-be-nl|sgn-ch-de|zh-guoyu|zh-hakka|zh-min|zh-min-nan|zh-xiang)';
    public const GRANDFATHERED = ['art-lojban','cel-gaulish','en-gb-oed','i-ami','i-bnn','i-default','i-enochian','i-hak','i-klingon','i-lux','i-mingo','i-navajo','i-pwn','i-tao','i-tay','i-tsu','no-bok','no-nyn','sgn-be-fr','sgn-be-nl','sgn-ch-de','zh-guoyu','zh-hakka','zh-min','zh-min-nan','zh-xiang'];
}
