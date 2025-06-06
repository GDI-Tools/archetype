<?php

namespace Archetype\Vendor\Illuminate\Database\Eloquent\Casts;

use Archetype\Vendor\Illuminate\Contracts\Database\Eloquent\Castable;
use Archetype\Vendor\Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Archetype\Vendor\Illuminate\Support\HtmlString;
class AsHtmlString implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @param  array  $arguments
     * @return \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\Illuminate\Support\HtmlString, string|HtmlString>
     */
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes
        {
            public function get($model, $key, $value, $attributes)
            {
                return isset($value) ? new HtmlString($value) : null;
            }
            public function set($model, $key, $value, $attributes)
            {
                return isset($value) ? (string) $value : null;
            }
        };
    }
}
