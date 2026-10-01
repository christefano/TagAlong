<?php

namespace Kanboard\Plugin\TagAlong\Transport;

class Mail extends \Kanboard\Core\Mail\Transport\Mail
{
    use ThreadHeaders;
}
