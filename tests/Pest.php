<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A streamed /v1/responses body carrying one text answer, so a test can hand
 * GPT a reply the same way the real API delivers it — in deltas, closed by
 * response.completed. Every service that calls the trait
 * CallsOpenAiResponsesApi needs this shape; faking a plain JSON body instead
 * would test a transport the app never uses.
 *
 * @return \GuzzleHttp\Promise\FulfilledPromise  What Http::fake() expects as a mapped response.
 */
function openAiStreamResponse(string $text)
{
    return Http::response(
        "event: response.output_text.delta\n"
        .'data: '.json_encode(['type' => 'response.output_text.delta', 'delta' => $text])."\n\n"
        ."event: response.completed\n"
        .'data: '.json_encode(['type' => 'response.completed'])."\n\n",
        200,
        ['Content-Type' => 'text/event-stream']
    );
}
