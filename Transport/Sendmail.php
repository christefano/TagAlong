<?php

namespace Kanboard\Plugin\TagAlong\Transport;

class Sendmail extends \Kanboard\Core\Mail\Transport\Sendmail
{
    use ThreadHeaders;
}
