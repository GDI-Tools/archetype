<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\Inflector\Rules\Portuguese;

use Archetype\Vendor\Doctrine\Inflector\GenericLanguageInflectorFactory;
use Archetype\Vendor\Doctrine\Inflector\Rules\Ruleset;
final class InflectorFactory extends GenericLanguageInflectorFactory
{
    protected function getSingularRuleset(): Ruleset
    {
        return Rules::getSingularRuleset();
    }
    protected function getPluralRuleset(): Ruleset
    {
        return Rules::getPluralRuleset();
    }
}
