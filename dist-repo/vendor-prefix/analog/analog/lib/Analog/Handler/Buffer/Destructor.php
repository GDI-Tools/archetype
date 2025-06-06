<?php

namespace Archetype\Vendor\Analog\Handler\Buffer;

/**
 * A destructor object to call close() for us at the end of the request.
 */
class Destructor
{
    public function __destruct()
    {
        \Archetype\Vendor\Analog\Handler\Buffer::close();
    }
}
