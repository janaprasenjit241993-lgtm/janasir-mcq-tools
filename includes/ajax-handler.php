<?php

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_jmt_get_criteria', 'jmt_get_criteria_ajax');
add_action('wp_ajax_nopriv_jmt_get_criteria', 'jmt_get_criteria_ajax');
add_action('wp_ajax_jmt_get_questions', 'jmt_get_questions_ajax');
add_action('wp_ajax_nopriv_jmt_get_questions', 'jmt_get_questions_ajax');

function jmt_validate_nonce() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (! wp_verify_nonce($nonce, 'jmt_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'janasir-mcq-tools')], 403);
    }
}

function jmt_get_criteria_ajax() {
    jmt_validate_nonce();

    $questions = jmt_get_questions_data();
    $tree = jmt_build_criteria_tree($questions);

    wp_send_json_success([
        'criteriaTree' => $tree,
        'totalQuestions' => count($questions),
    ]);
}

function jmt_get_questions_ajax() {
    jmt_validate_nonce();

    $medium = isset($_POST['medium']) ? jmt_clean_text(wp_unslash($_POST['medium'])) : '';
    $exam = isset($_POST['exam']) ? jmt_clean_text(wp_unslash($_POST['exam'])) : '';
    $subject = isset($_POST['subject']) ? jmt_clean_text(wp_unslash($_POST['subject'])) : '';
    $chapter = isset($_POST['chapter']) ? jmt_clean_text(wp_unslash($_POST['chapter'])) : '';
    $topic = isset($_POST['topic']) ? jmt_clean_text(wp_unslash($_POST['topic'])) : '';

    $questions = jmt_get_questions_data();
    $filtered = [];

    foreach ($questions as $q) {
        if ($q['medium'] !== $medium) {
            continue;
        }
        if ($q['exam'] !== $exam) {
            continue;
        }
        if ($q['subject'] !== $subject) {
            continue;
        }
        if ($q['chapter'] !== $chapter) {
            continue;
        }
        if ($q['topic'] !== $topic) {
            continue;
        }
        $filtered[] = $q;
    }

    wp_send_json_success([
        'questions' => $filtered,
    ]);
}
