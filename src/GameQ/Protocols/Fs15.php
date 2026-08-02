<?php

/**
 * This file is part of GameQ.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */

namespace GameQ\Protocols;

class Fs15 extends Farmingsimulator
{
    protected string $name = 'fs15';

    protected string $name_long = 'Farming Simulator 2015';

    protected string $responseFormat = 'xml';
}
