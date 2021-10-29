<?php

namespace Knuckles\Scribe\Exceptions;

/**
 * Scribe Exceptions are thrown intentionally by us, and should not be swallowed.
 * They are meant to crash the task and be thrown back to the user.
 */
interface ScribeException
{
}
