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
     * {@inheritdoc}
     */
    public function find(int $id);

    /**
     * {@inheritdoc}
     */
    public function getForm();

    /**
     * {@inheritdoc}
     */
    public function getEntity();

}
