<?php

namespace Kanboard\Plugin\TagAlong\Transport;

class Smtp extends \Kanboard\Core\Mail\Transport\Smtp
{
    use ThreadHeaders;
}
