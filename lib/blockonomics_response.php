<?php
/**
 * Blockonomics API Response
 *
 * @package blesta
 * @subpackage blesta.components.modules.blockonomics
 * @copyright Copyright (c) 2024, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class BlockonomicsResponse
{
    private $status;
    private $raw;
    private $response;
    private $errors;

    /**
     * BlockonomicsResponse constructor.
     *
     * @param mixed $response The RAW response from the API
     */
    public function __construct($response)
    {
        $this->raw = $response;
        $this->response = json_decode($response);
        $this->status = $this->response->status ?? 200;

        if (empty($this->response)) {
            $this->status = 400;
        }

        // Set errors
        $this->errors = [];
        if ($this->status >= 400) {
            $this->errors[] = $this->response->message ?? 'Unknown error';
        }
    }

    /**
     * Get the status of this response
     *
     * @return string The status of this response
     */
    public function status()
    {
        return $this->status;
    }

    /**
     * Get the raw data from this response
     *
     * @return string The raw data from this response
     */
    public function raw()
    {
        return $this->raw;
    }

    /**
     * Get the data response from this response
     *
     * @return string The data response from this response
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * Get any errors from this response
     *
     * @return array The errors from this response
     */
    public function errors()
    {
        return $this->errors;
    }
}
