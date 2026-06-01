<?php

namespace Afterburner\Meetings\Tests\Unit;

use Afterburner\Meetings\Support\MeetingsDocumentLinkUi;
use Afterburner\Meetings\Tests\TestCase;

class MeetingsDocumentLinkUiTest extends TestCase
{
    public function test_search_is_inactive_until_minimum_length(): void
    {
        $this->assertFalse(MeetingsDocumentLinkUi::searchIsActive(''));
        $this->assertFalse(MeetingsDocumentLinkUi::searchIsActive('a'));
        $this->assertTrue(MeetingsDocumentLinkUi::searchIsActive('ag'));
        $this->assertTrue(MeetingsDocumentLinkUi::searchIsActive('  agenda  '));
    }
}
