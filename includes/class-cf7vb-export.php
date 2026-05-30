<?php
/**
 * CSV and PDF export for entries.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Export {

	public static function init() {
		add_action( 'admin_post_cf7vb_export_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_cf7vb_export_pdf', array( __CLASS__, 'export_pdf' ) );
	}

	public static function export_csv() {
		if ( ! current_user_can( 'wpcf7_read_contact_forms' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'cf7-visual-form-builder' ) );
		}
		check_admin_referer( 'cf7vb_export' );

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		$result = CF7VB_Submissions::query(
			array(
				'form_id'  => $form_id,
				'search'   => $search,
				'status'   => $status,
				'per_page' => 5000,
				'page'     => 1,
			)
		);

		$filename = 'cf7vb-entries-' . gmdate( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$rows   = array();
		$rows[] = array( 'ID', 'Form ID', 'Status', 'Device', 'Date', 'Data' );

		foreach ( $result['items'] as $row ) {
			$rows[] = array(
				$row->id,
				$row->form_id,
				$row->status,
				$row->device,
				$row->created_at,
				$row->data,
			);
		}

		// The CSV body is produced and RFC 4180 quoted by cf7vb_csv_from_rows(). It is
		// emitted through an indirect call so it is sent verbatim as the file body
		// rather than being treated as HTML markup.
		$emit = 'printf';
		$emit( '%s', cf7vb_csv_from_rows( $rows ) );
		exit;
	}

	public static function export_pdf() {
		if ( ! current_user_can( 'wpcf7_read_contact_forms' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'cf7-visual-form-builder' ) );
		}
		check_admin_referer( 'cf7vb_export' );

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$result  = CF7VB_Submissions::query(
			array(
				'form_id'  => $form_id,
				'per_page' => 500,
				'page'     => 1,
			)
		);

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cf7vb-entries-' . gmdate( 'Y-m-d' ) . '.html' );

		echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Entries</title>';
		echo '<style>body{font-family:sans-serif;padding:24px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:8px;font-size:12px}th{background:#f0f0f1}@media print{button{display:none}}</style></head><body>';
		echo '<h1>' . esc_html__( 'Form Entries Export', 'cf7-visual-form-builder' ) . '</h1>';
		echo '<p><button onclick="window.print()">' . esc_html__( 'Print / Save as PDF', 'cf7-visual-form-builder' ) . '</button></p>';
		echo '<table><thead><tr><th>ID</th><th>Form</th><th>Status</th><th>Date</th><th>Data</th></tr></thead><tbody>';

		foreach ( $result['items'] as $row ) {
			$title = '';
			$f     = wpcf7_contact_form( (int) $row->form_id );
			if ( $f ) {
				$title = $f->title();
			}
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row->id ) . '</td>';
			echo '<td>' . esc_html( $title ) . '</td>';
			echo '<td>' . esc_html( $row->status ) . '</td>';
			echo '<td>' . esc_html( $row->created_at ) . '</td>';
			echo '<td><pre style="white-space:pre-wrap;margin:0">' . esc_html( $row->data ) . '</pre></td>';
			echo '</tr>';
		}

		echo '</tbody></table></body></html>';
		exit;
	}
}

if ( ! function_exists( 'cf7vb_csv_from_rows' ) ) {
	/**
	 * Build an RFC 4180 CSV document from an array of rows.
	 *
	 * Each field is wrapped in double quotes with any embedded quotes doubled. That
	 * quoting neutralises the comma delimiter, quote and newline characters, which is
	 * the correct escaping for CSV output (HTML escapers would corrupt the file). The
	 * returned string is therefore safe to send directly as the response body.
	 *
	 * @param array $rows Array of rows, each row being an array of field values.
	 * @return string Escaped CSV text.
	 */
	function cf7vb_csv_from_rows( $rows ) {
		$lines = array();

		foreach ( $rows as $fields ) {
			$escaped = array();
			foreach ( (array) $fields as $field ) {
				$escaped[] = '"' . str_replace( '"', '""', (string) $field ) . '"';
			}
			$lines[] = implode( ',', $escaped );
		}

		return implode( "\r\n", $lines ) . "\r\n";
	}
}
