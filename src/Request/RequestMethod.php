<?php

namespace HMRC\Request;

abstract class RequestMethod
{
    /** @var string constant for method POST */
    public const POST = 'POST';

    /** @var string constant for method PUT */
    public const PUT = 'PUT';

    /** @var string constant for method GET */
    public const GET = 'GET';

    /** @var string constant for method DELETE */
    public const DELETE = 'DELETE';
}
