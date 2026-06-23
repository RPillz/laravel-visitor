<?php

namespace RPillz\LaravelVisitor\Facades;

use Illuminate\Support\Facades\Facade;
use RPillz\LaravelVisitor\LaravelVisitor;

/**
 * @see LaravelVisitor
 *
 * @method static void track(\Illuminate\Http\Request $request)
 * @method static static anonymous()
 */
class Visitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelVisitor::class;
    }
}
