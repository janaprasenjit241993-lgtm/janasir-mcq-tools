<?php

if (! defined('ABSPATH')) {
    exit;
}

function jmt_get_upload_dir() {
    $uploads = wp_upload_dir();
    $dir = trailingslashit($uploads['basedir']) . JMT_UPLOAD_SUBDIR;

    if (! file_exists($dir)) {
        wp_mkdir_p($dir);
    }

    return $dir;
}

function jmt_handle_csv_upload($file) {
    $allowed_mimes = [
        'csv' => 'text/csv',
        'txt' => 'text/plain',
    ];

    $upload = wp_handle_upload($file, [
        'test_form' => false,
        'mimes'     => $allowed_mimes,
    ]);

    if (isset($upload['error'])) {
        return new WP_Error('jmt_upload_error', $upload['error']);
    }

    $source = $upload['file'];
    $target_dir = jmt_get_upload_dir();
    $filename = sanitize_file_name(wp_basename($source));
    $target = trailingslashit($target_dir) . time() . '-' . $filename;

    if (! rename($source, $target)) {
        return new WP_Error('jmt_move_error', __('Unable to move uploaded file.', 'janasir-mcq-tools'));
    }

    return [
        'path' => $target,
    ];
}

function jmt_get_csv_files() {
    $dir = jmt_get_upload_dir();
    $files = glob(trailingslashit($dir) . '*.csv');

    if (! is_array($files)) {
        return [];
    }

    $result = [];
    foreach ($files as $file) {
        $result[] = [
            'path' => $file,
            'name' => wp_basename($file),
            'rows' => max(0, (int) jmt_count_csv_rows($file) - 1),
        ];
    }

    return $result;
}

function jmt_count_csv_rows($file) {
    if (! is_readable($file)) {
        return 0;
    }

    $count = 0;
    $handle = fopen($file, 'r');
    if (! $handle) {
        return 0;
    }

    while (fgetcsv($handle) !== false) {
        $count++;
    }

    fclose($handle);
    return $count;
}

function jmt_delete_csv_file($file) {
    if (! jmt_is_valid_csv_path($file)) {
        return false;
    }

    return unlink($file);
}

function jmt_is_valid_csv_path($file) {
    $real_file = realpath($file);
    $real_dir = realpath(jmt_get_upload_dir());

    if (! $real_file || ! $real_dir) {
        return false;
    }

    if (strpos($real_file, $real_dir) !== 0) {
        return false;
    }

    return is_file($real_file) && strtolower(pathinfo($real_file, PATHINFO_EXTENSION)) === 'csv';
}

function jmt_get_active_csv_file() {
    $file = get_option('jmt_active_csv_file', '');

    if ($file && jmt_is_valid_csv_path($file)) {
        return $file;
    }

    $files = jmt_get_csv_files();
    if (empty($files)) {
        return '';
    }

    update_option('jmt_active_csv_file', $files[0]['path']);
    return $files[0]['path'];
}

function jmt_flush_cache() {
    delete_transient('jmt_csv_data_hash');
    delete_transient('jmt_csv_questions');
    delete_transient('jmt_csv_criteria');
}

function jmt_get_questions_data() {
    $file = jmt_get_active_csv_file();
    if (! $file) {
        return [];
    }

    $hash = md5_file($file);
    $stored_hash = get_transient('jmt_csv_data_hash');
    $cached_questions = get_transient('jmt_csv_questions');

    if ($hash && $stored_hash === $hash && is_array($cached_questions)) {
        return $cached_questions;
    }

    $questions = jmt_parse_csv_questions($file);

    set_transient('jmt_csv_data_hash', $hash, DAY_IN_SECONDS);
    set_transient('jmt_csv_questions', $questions, DAY_IN_SECONDS);
    delete_transient('jmt_csv_criteria');

    return $questions;
}

function jmt_parse_csv_questions($file) {
    if (! jmt_is_valid_csv_path($file) || ! is_readable($file)) {
        return [];
    }

    $handle = fopen($file, 'r');
    if (! $handle) {
        return [];
    }

    $headers = fgetcsv($handle);
    if (! is_array($headers)) {
        fclose($handle);
        return [];
    }

    $normalized_headers = array_map('jmt_normalize_header', $headers);
    $questions = [];

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < count($normalized_headers)) {
            $row = array_pad($row, count($normalized_headers), '');
        }

        $item = array_combine($normalized_headers, $row);
        if (! is_array($item)) {
            continue;
        }

        $question = [
            'medium'      => jmt_clean_text($item['medium'] ?? ''),
            'exam'        => jmt_clean_text($item['exam'] ?? ''),
            'subject'     => jmt_clean_text($item['subject'] ?? ''),
            'chapter'     => jmt_clean_text($item['chapter'] ?? ''),
            'topic'       => jmt_clean_text($item['topic'] ?? ''),
            'question'    => jmt_clean_text($item['question'] ?? ''),
            'option_a'    => jmt_clean_text($item['option_a'] ?? ''),
            'option_b'    => jmt_clean_text($item['option_b'] ?? ''),
            'option_c'    => jmt_clean_text($item['option_c'] ?? ''),
            'option_d'    => jmt_clean_text($item['option_d'] ?? ''),
            'correct'     => strtoupper(jmt_clean_text($item['correct'] ?? '')),
            'explanation' => jmt_clean_text($item['explanation'] ?? ''),
            'link'        => esc_url_raw($item['link'] ?? ''),
        ];

        if (! in_array($question['correct'], ['A', 'B', 'C', 'D'], true)) {
            continue;
        }

        if ($question['question'] === '') {
            continue;
        }

        $questions[] = $question;
    }

    fclose($handle);
    return $questions;
}

function jmt_normalize_header($header) {
    $header = strtolower(trim((string) $header));
    $header = str_replace([' ', '-'], '_', $header);
    return $header;
}

function jmt_clean_text($value) {
    $value = is_scalar($value) ? (string) $value : '';
    $value = wp_kses($value, []);
    return trim($value);
}

function jmt_build_criteria_tree($questions) {
    $cached = get_transient('jmt_csv_criteria');
    if (is_array($cached)) {
        return $cached;
    }

    $tree = [];

    foreach ($questions as $q) {
        $medium = $q['medium'];
        $exam = $q['exam'];
        $subject = $q['subject'];
        $chapter = $q['chapter'];
        $topic = $q['topic'];

        if (! isset($tree[$medium])) {
            $tree[$medium] = [];
        }
        if (! isset($tree[$medium][$exam])) {
            $tree[$medium][$exam] = [];
        }
        if (! isset($tree[$medium][$exam][$subject])) {
            $tree[$medium][$exam][$subject] = [];
        }
        if (! isset($tree[$medium][$exam][$subject][$chapter])) {
            $tree[$medium][$exam][$subject][$chapter] = [];
        }

        if (! in_array($topic, $tree[$medium][$exam][$subject][$chapter], true)) {
            $tree[$medium][$exam][$subject][$chapter][] = $topic;
        }
    }

    set_transient('jmt_csv_criteria', $tree, DAY_IN_SECONDS);
    return $tree;
}
