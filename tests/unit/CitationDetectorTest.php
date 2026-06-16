<?php
/**
 * Tests for PRV_Citation_Detector: domain extraction and cite detection.
 *
 * @package PrVision
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers PRV_Citation_Detector
 */
class CitationDetectorTest extends TestCase {

	private PRV_Citation_Detector $detector;

	protected function setUp(): void {
		prv_test_reset();
		$this->detector = new PRV_Citation_Detector();
	}

	public function test_parse_plain_url_strings(): void {
		$domains = $this->detector->parse_domains( [
			'https://peptiderepo.com/bpc-157/',
			'https://www.examine.com/supplements/bpc-157/',
			'https://pubmed.ncbi.nlm.nih.gov/12345',
		] );

		$this->assertContains( 'peptiderepo.com', $domains, 'parse_domains: peptiderepo.com extracted from URL' );
		$this->assertContains( 'examine.com', $domains, 'parse_domains: www. stripped from examine.com' );
		$this->assertContains( 'pubmed.ncbi.nlm.nih.gov', $domains, 'parse_domains: full subdomain preserved when not www.' );
		$this->assertCount( 3, $domains, 'parse_domains: exactly 3 unique domains' );
	}

	public function test_parse_object_style_citations_deduplicates(): void {
		$domains = $this->detector->parse_domains( [
			[ 'url' => 'https://peptiderepo.com/tb-500/' ],
			[ 'url' => 'https://examine.com/tb500' ],
			[ 'url' => 'https://peptiderepo.com/bpc-157/' ], // duplicate domain
		] );

		$this->assertCount( 2, $domains, 'parse_domains: object-style citations, duplicates removed' );
		$this->assertContains( 'peptiderepo.com', $domains, 'parse_domains: object-style, peptiderepo.com present' );
	}

	public function test_parse_empty_input_returns_empty_array(): void {
		$domains = $this->detector->parse_domains( [] );
		$this->assertSame( [], $domains, 'parse_domains: empty input returns empty array' );
	}

	public function test_is_cited_returns_true_when_target_present(): void {
		$this->assertTrue( $this->detector->is_cited( ['peptiderepo.com', 'examine.com'] ) );
	}

	public function test_is_cited_returns_false_when_target_absent(): void {
		$this->assertFalse( $this->detector->is_cited( ['examine.com', 'pubmed.ncbi.nlm.nih.gov'] ) );
	}

	public function test_is_cited_returns_false_for_empty_list(): void {
		$this->assertFalse( $this->detector->is_cited( [] ) );
	}

	public function test_get_our_position_returns_1based_index(): void {
		$domains = ['examine.com', 'peptiderepo.com', 'pubmed.ncbi.nlm.nih.gov'];
		$this->assertSame( 2, $this->detector->get_our_position( $domains ), 'position=2' );

		$domains_first = ['peptiderepo.com', 'examine.com'];
		$this->assertSame( 1, $this->detector->get_our_position( $domains_first ), 'position=1 when first' );
	}

	public function test_get_our_position_returns_null_when_not_present(): void {
		$domains_absent = ['examine.com', 'pubmed.ncbi.nlm.nih.gov'];
		$this->assertNull( $this->detector->get_our_position( $domains_absent ), 'null when not present' );
	}

	public function test_parse_gracefully_skips_malformed_items(): void {
		$domains = $this->detector->parse_domains( [
			'not-a-url',
			42,
			null,
			['no_url_key' => 'foo'],
			['url' => ''],
		] );
		$this->assertSame( [], $domains, 'parse_domains: gracefully skips malformed items' );
	}

	public function test_p2a_www_strip_does_not_corrupt_w_domains(): void {
		$domains = $this->detector->parse_domains( [
			'https://wikipedia.org/wiki/bpc-157',
			'https://www.examine.com/bpc-157/',
			'https://webmd.com/drug/bpc-157',
		] );

		$this->assertContains( 'wikipedia.org', $domains, 'P2-A: wikipedia.org survives intact' );
		$this->assertContains( 'examine.com', $domains, 'P2-A: www.examine.com correctly stripped' );
		$this->assertContains( 'webmd.com', $domains, 'P2-A: webmd.com survives intact' );
		$this->assertCount( 3, $domains, 'P2-A: exactly 3 unique domains' );
	}
}
