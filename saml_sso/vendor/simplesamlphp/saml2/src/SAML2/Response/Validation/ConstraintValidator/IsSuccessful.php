<?php

class SAML2_Response_Validation_ConstraintValidator_IsSuccessful implements
    SAML2_Response_Validation_ConstraintValidator
{
    public function validate(
        SAML2_Response $response,
        SAML2_Response_Validation_Result $result
    ) {
        if (!$response->isSuccess()) {
            $result->addError($this->buildMessage($response->getStatus()));
        }
    }

    /**
     * @param array $responseStatus
     *
     * @return string
     */
    private function buildMessage(array $responseStatus)
    {
        return sprintf(
            '%s%s%s',
            $this->truncateStatus($responseStatus['Code']),
            $responseStatus['SubCode'] ? '/' . $this->truncateStatus($responseStatus['SubCode']) : '',
            $responseStatus['Message'] ? ' ' . $responseStatus['Message'] : ''
        );
    }

    /**
     * Truncate the status if it is prefixed by its urn.
     * @param string $status
     *
     * @return string
     */
    private function truncateStatus($status)
    {
        $prefixLength = strlen(SAML2_Const::STATUS_PREFIX);
        if (strpos($status, SAML2_Const::STATUS_PREFIX) !== 0) {
            return $status;
        }

        return substr($status, $prefixLength);
    }
}
