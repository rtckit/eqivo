<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response;

class Error
{
    public const DEFAULT_BODY = [
        0 => <<<EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<title>500 Internal Server Error</title>
<h1>Internal Server Error</h1>
<p>Unspecified Error</p>
EOD,

        400 => <<<EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<title>400 Bad Request</title>
<h1>Bad Request</h1>
<p>Cannot process request</p>
EOD,

        401 => <<<EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<title>401 Unauthorized</title>
<h1>Unauthorized</h1>
<p>HTTP Auth Failed</p>
EOD,

        404 => <<<EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<title>404 Not Found</title>
<h1>Not Found</h1>
<p>The requested URL was not found on the server. If you entered the URL manually please check your spelling and try again.</p>
EOD,

        405 => <<<EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<title>405 Method Not Allowed</title>
<h1>Method Not Allowed</h1>
<p>Unsupported method</p>
EOD,
    ];

    public int $code;

    public string $body;
}
