<?php
/**
 * Tests for the visibility score formula in PRV_Ai_Visibility_Collector.
 *
 * Formula: score = round((cited/total + position_sum/total) / 2, 4)
 *
 * @package PrVision
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers PRV_Ai_Visibility_Collector
 */
class ScoreCalcTest extends TestCase {

	private PRV_Ai_Visibility_Collector $collector;

	protected function setUp(): void {
		prv_test_reset();
		$this->collector = new PRV_Ai_Visibility_Collector( null );
	}

	public function test_zero_total_returns_zero(): void {
		$this->assertSame( 0.0, $this->collector->compute_score( 0, 0, 0.0 ) );
	}

	public function test_all_cited_position_1_returns_1(): void {
		$this->assertSame( 1.0, $this->collector->compute_score( 10, 10, 10.0 ) );
	}

	public function test_none_cited_returns_zero(): void {
		$this->assertSame( 0.0, $this->collector->compute_score( 0, 10, 0.0 ) );
	}

	public function test_half_cited_at_position_2(): void {
		// base=0.5, bonus=0.25, score=0.375
		$this->assertSame( 0.375, $this->collector->compute_score( 5, 10, 2.5 ) );
	}

	public function test_single_probe_cited_at_position_3(): void {
		// base=1.0, bonus=0.3333, score=0.6667
		$this->assertSame( 0.6667, $this->collector->compute_score( 1, 1, 1.0 / 3.0 ) );
	}

	public function test_score_bounded_within_0_and_1(): void {
		$this->assertLessThanOrEqual( 1.0, $this->collector->compute_score( 1, 1, 1.0 ) );
		$this->assertGreaterThanOrEqual( 0.0, $this->collector->compute_score( 0, 10, 0.0 ) );
	}

	public function test_four_decimal_precision(): void {
		$expected = round( ( 3.0 / 7.0 + 1.5 / 7.0 ) / 2.0, 4 );
		$this->assertSame( $expected, $this->collector->compute_score( 3, 7, 1.5 ) );
	}
}
