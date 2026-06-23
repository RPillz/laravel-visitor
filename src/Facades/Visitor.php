<?php

namespace RPillz\LaravelVisitor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RPillz\LaravelVisitor\LaravelVisitor
 *
 * @method static void track(\Illuminate\Http\Request $request)
 * @method static static anonymous()
 */
class Visitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \RPillz\LaravelVisitor\LaravelVisitor::class;
    }
}
