<?php
/**
 * Tests for provider response parsing (Perplexity + OpenRouter).
 *
 * @package PrVision
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers PRV_Perplexity_Provider
 * @covers PRV_OpenRouter_Provider
 */
class ProviderParsingTest extends TestCase {

	private PRV_Perplexity_Provider $perplexity;
	private PRV_OpenRouter_Provider $openrouter;

	protected function setUp(): void {
		prv_test_reset();
		$detector         = new PRV_Citation_Detector();
		$this->perplexity = new PRV_Perplexity_Provider( null, $detector );
		$this->openrouter = new PRV_OpenRouter_Provider( 'openai/gpt-4o-search-preview', null, $detector );
	}

	public function test_perplexity_cited_via_citations_array(): void {
		$data   = [ 'choices' => [ [ 'message' => [ 'content' => 'BPC-157 is a peptide.' ] ] ], 'citations' => [ 'https://peptiderepo.com/bpc-157/', 'https://examine.com/bpc-157' ], 'usage' => [ 'total_tokens' => 500 ] ];
		$result = $this->perplexity->parse_response( $data );

		$this->assertTrue( $result->is_cited() );
		$this->assertSame( 1, $result->get_our_position() );
		$this->assertCount( 2, $result->get_source_domains() );
		$this->assertSame( 500 * 0.000001, $result->get_cost_usd() );
	}

	public function test_perplexity_not_cited_empty_citations(): void {
		$data   = [ 'choices' => [ [ 'message' => [ 'content' => 'Info here.' ] ] ], 'citations' => [], 'usage' => [ 'total_tokens' => 200 ] ];
		$result = $this->perplexity->parse_response( $data );

		$this->assertFalse( $result->is_cited() );
		$this->assertNull( $result->get_our_position() );
	}

	public function test_perplexity_excerpt_truncated_to_500_chars(): void {
		$data   = [ 'choices' => [ [ 'message' => [ 'content' => str_repeat( 'a', 600 ) ] ] ], 'citations' => [] ];
		$result = $this->perplexity->parse_response( $data );
		$this->assertSame( 500, mb_strlen( $result->get_raw_excerpt() ) );
	}

	public function test_perplexity_object_style_citations(): void {
		$data   = [ 'choices' => [ [ 'message' => [ 'content' => 'answer' ] ] ], 'citations' => [ [ 'url' => 'https://peptiderepo.com/mk-677/' ], [ 'url' => 'https://examine.com/mk677' ] ] ];
		$result = $this->perplexity->parse_response( $data );

		$this->assertTrue( $result->is_cited() );
		$this->assertSame( 1, $result->get_our_position() );
	}

	public function test_perplexity_fallback_cost_when_no_usage(): void {
		$data   = [ 'choices' => [ [ 'message' => [ 'content' => 'answer' ] ] ], 'citations' => [] ];
		$result = $this->perplexity->parse_response( $data );
		$this->assertSame( PRV_Perplexity_Provider::ESTIMATED_COST_PER_PROBE, $result->get_cost_usd() );
	}

	public function test_openrouter_annotations_path(): void {
		$data = [ 'choices' => [ [ 'message' => [ 'content' => 'BPC-157 reconstitution.', 'annotations' => [ [ 'url_citation' => [ 'url' => 'https://peptiderepo.com/bpc-157-reconstitution/' ] ], [ 'url_citation' => [ 'url' => 'https://examine.com/bpc-157' ] ] ] ] ] ], 'usage' => [ 'total_tokens' => 300 ] ];
		$result = $this->openrouter->parse_response( $data );

		$this->assertTrue( $result->is_cited() );
		$this->assertSame( 1, $result->get_our_position() );
	}

	public function test_openrouter_regex_fallback(): void {
		$data   = [ 'choices' => [ [ 'message' => [ 'content' => 'See https://peptiderepo.com/tb-500/ and https://examine.com/tb-500 for details.' ] ] ], 'usage' => [ 'total_tokens' => 150 ] ];
		$result = $this->openrouter->parse_response( $data );
		$this->assertTrue( $result->is_cited() );
	}

	public function test_openrouter_not_cited(): void {
		$data   = [ 'choices' => [ [ 'message' => [ 'content' => 'Some response without our domain.' ] ] ] ];
		$result = $this->openrouter->parse_response( $data );
		$this->assertFalse( $result->is_cited() );
	}

	public function test_openrouter_cost_from_token_usage(): void {
		$data   = [ 'choices' => [ [ 'message' => [ 'content' => 'test' ] ] ], 'usage' => [ 'total_tokens' => 1000 ] ];
		$result = $this->openrouter->parse_response( $data );
		$this->assertSame( 1000 * 0.000002, $result->get_cost_usd() );
	}
}
