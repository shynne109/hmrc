<?php

namespace HMRC\Scope;

class Scope
{
    /** @var string hello scope */
    const HELLO = 'hello';

    /** @var string read:vat scope https://developer.service.hmrc.gov.uk/api-documentation/docs/api/service/vat-api/1.0#_retrieve-vat-obligations_get_accordion */
    const VAT_READ = 'read:vat';

    /** @var string write:vat scope https://developer.service.hmrc.gov.uk/api-documentation/docs/api/service/vat-api/1.0#_retrieve-vat-obligations_get_accordion */
    const VAT_WRITE = 'write:vat';

    const NATIONAL_INSURANCE_READ = 'read:national-insurance';

    const SELF_ASSESSMENT_READ = 'read:self-assessment';
    const SELF_ASSESSMENT_WRITE = 'write:self-assessment';

    const SELF_ASSESSMENT_ASSIST_READ = 'read:self-assessment-assist';
    const SELF_ASSESSMENT_ASSIST_WRITE = 'write:self-assessment-assist';

    const AGENT_SENT_INVITATION_READ = 'read:sent-invitations'; // Grants read access
    const AGENT_CHECK_RELATIONSHIP_READ = 'read:check-relationship'; // Grants read access
    const AGENT_SENT_INVITATION_WRITE = 'write:sent-invitations'; // - Grants write access
    const AGENT_CHECK_RELATIONSHIP_WRITE = 'write:cancel-invitations';




}
