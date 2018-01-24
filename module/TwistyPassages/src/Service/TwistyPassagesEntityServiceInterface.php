<?php

/**
 * TWisty Passages Entity Service Interface.
 * TWisty Passages Entity Service Interface.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

interface TwistyPassagesEntityServiceInterface
{

    /**
     * {@inheritdoc}
     */
    public function getClassName(): string;

    /**
     * @param int $id
     * @return null|object
     */
    public function find(int $id);

}
