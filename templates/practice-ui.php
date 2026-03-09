<?php if (! defined('ABSPATH')) { exit; } ?>
<div id="jmt-app" class="jmt-app">
    <h2><?php echo esc_html__('Select Practice Criteria', 'janasir-mcq-tools'); ?></h2>

    <div class="jmt-filter-grid">
        <label>
            <?php echo esc_html__('Medium', 'janasir-mcq-tools'); ?>
            <select id="jmt-medium"><option value=""><?php echo esc_html__('Select Medium', 'janasir-mcq-tools'); ?></option></select>
        </label>
        <label>
            <?php echo esc_html__('Exam Type', 'janasir-mcq-tools'); ?>
            <select id="jmt-exam" disabled><option value=""><?php echo esc_html__('Select Exam Type', 'janasir-mcq-tools'); ?></option></select>
        </label>
        <label>
            <?php echo esc_html__('Subject', 'janasir-mcq-tools'); ?>
            <select id="jmt-subject" disabled><option value=""><?php echo esc_html__('Select Subject', 'janasir-mcq-tools'); ?></option></select>
        </label>
        <label>
            <?php echo esc_html__('Chapter', 'janasir-mcq-tools'); ?>
            <select id="jmt-chapter" disabled><option value=""><?php echo esc_html__('Select Chapter', 'janasir-mcq-tools'); ?></option></select>
        </label>
        <label>
            <?php echo esc_html__('Topic', 'janasir-mcq-tools'); ?>
            <select id="jmt-topic" disabled><option value=""><?php echo esc_html__('Select Topic', 'janasir-mcq-tools'); ?></option></select>
        </label>
    </div>

    <button id="jmt-start" class="button button-primary" disabled><?php echo esc_html__('Start Practice', 'janasir-mcq-tools'); ?></button>

    <div id="jmt-status" class="jmt-status" aria-live="polite"></div>
    <div id="jmt-questions" class="jmt-questions"></div>
    <div id="jmt-performance" class="jmt-performance" hidden></div>
</div>
