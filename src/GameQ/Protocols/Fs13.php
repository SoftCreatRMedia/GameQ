<?php

/**
 * This file is part of GameQ.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */

namespace GameQ\Protocols;

class Fs13 extends Farmingsimulator
{
    protected string $name = 'fs13';

    protected string $name_long = 'Farming Simulator 2013';

    protected string $responseFormat = 'xml';
}
