<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppTestCase;

final class SurveyTest extends AppTestCase
{
    public function testNewSurveyLeadsToTheQuestions(): void
    {
        $this->createOrganization($this->login());
        $this->client->request('GET', '/surveys/new');
        self::assertSelectorTextContains('form', 'im nächsten Schritt');

        $this->client->submitForm('Weiter zu den Fragen', ['survey_form[title]' => 'Schulweg']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#detail-heading', 'Schulweg');
        self::assertSelectorExists('button:contains("Frage hinzufügen")');
    }
}
