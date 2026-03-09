(function ($) {
  'use strict';

  const app = $('#jmt-app');
  if (!app.length) return;

  const state = {
    tree: {},
    questions: [],
    currentIndex: 0,
    startedAt: null,
    attempted: 0,
    correct: 0,
    wrong: 0,
  };

  const els = {
    medium: $('#jmt-medium'),
    exam: $('#jmt-exam'),
    subject: $('#jmt-subject'),
    chapter: $('#jmt-chapter'),
    topic: $('#jmt-topic'),
    start: $('#jmt-start'),
    status: $('#jmt-status'),
    questions: $('#jmt-questions'),
    performance: $('#jmt-performance'),
  };

  function renderOptions(selectEl, options, placeholder, enabled) {
    const html = ['<option value="">' + placeholder + '</option>'];
    options.forEach((value) => {
      html.push('<option value="' + escapeHtml(value) + '">' + escapeHtml(value) + '</option>');
    });
    selectEl.html(html.join(''));
    selectEl.prop('disabled', !enabled);
  }

  function resetFrom(level) {
    if (level <= 1) renderOptions(els.exam, [], 'Select Exam Type', false);
    if (level <= 2) renderOptions(els.subject, [], 'Select Subject', false);
    if (level <= 3) renderOptions(els.chapter, [], 'Select Chapter', false);
    if (level <= 4) renderOptions(els.topic, [], 'Select Topic', false);
    els.start.prop('disabled', true);
  }

  function loadCriteria() {
    els.status.text(JMT_CONFIG.messages.loading);
    $.post(JMT_CONFIG.ajaxUrl, { action: 'jmt_get_criteria', nonce: JMT_CONFIG.nonce })
      .done((res) => {
        if (!res.success) return;
        state.tree = res.data.criteriaTree || {};
        const mediums = Object.keys(state.tree).filter(Boolean);
        renderOptions(els.medium, mediums, 'Select Medium', true);
        resetFrom(1);
        els.status.text('Loaded ' + (res.data.totalQuestions || 0) + ' questions.');
      })
      .fail(() => {
        els.status.text('Failed to load criteria.');
      });
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function maybeTypeset() {
    if (window.MathJax && typeof window.MathJax.typesetPromise === 'function') {
      window.MathJax.typesetPromise();
    }
  }

  function renderQuestion() {
    const q = state.questions[state.currentIndex];
    if (!q) {
      renderPerformance();
      return;
    }

    const html = `
      <div class="jmt-question-card" data-index="${state.currentIndex}">
        <h3>Question ${state.currentIndex + 1} of ${state.questions.length}</h3>
        <div class="jmt-question-text">${q.question}</div>
        <div class="jmt-options">
          <button class="jmt-option" data-option="A">A. ${q.option_a}</button>
          <button class="jmt-option" data-option="B">B. ${q.option_b}</button>
          <button class="jmt-option" data-option="C">C. ${q.option_c}</button>
          <button class="jmt-option" data-option="D">D. ${q.option_d}</button>
        </div>
        <div class="jmt-explanation" hidden></div>
      </div>
    `;

    els.questions[0].innerHTML = html;
    maybeTypeset();
  }

  function renderPerformance() {
    const seconds = Math.max(0, Math.floor((Date.now() - state.startedAt) / 1000));
    els.performance.html(`
      <h3>Performance Panel</h3>
      <p><strong>Total questions attempted:</strong> ${state.attempted}</p>
      <p><strong>Correct answers:</strong> ${state.correct}</p>
      <p><strong>Wrong answers:</strong> ${state.wrong}</p>
      <p><strong>Time taken:</strong> ${seconds} seconds</p>
    `);
    els.performance.prop('hidden', false);
    els.status.text('Practice completed.');
    els.questions.empty();
  }

  els.medium.on('change', function () {
    const medium = $(this).val();
    resetFrom(1);
    if (!medium || !state.tree[medium]) return;
    renderOptions(els.exam, Object.keys(state.tree[medium]).filter(Boolean), 'Select Exam Type', true);
  });

  els.exam.on('change', function () {
    const medium = els.medium.val();
    const exam = $(this).val();
    resetFrom(2);
    if (!medium || !exam || !state.tree[medium] || !state.tree[medium][exam]) return;
    renderOptions(els.subject, Object.keys(state.tree[medium][exam]).filter(Boolean), 'Select Subject', true);
  });

  els.subject.on('change', function () {
    const medium = els.medium.val();
    const exam = els.exam.val();
    const subject = $(this).val();
    resetFrom(3);
    if (!subject) return;
    const chapters = Object.keys(state.tree[medium][exam][subject] || {}).filter(Boolean);
    renderOptions(els.chapter, chapters, 'Select Chapter', true);
  });

  els.chapter.on('change', function () {
    const medium = els.medium.val();
    const exam = els.exam.val();
    const subject = els.subject.val();
    const chapter = $(this).val();
    resetFrom(4);
    if (!chapter) return;
    const topics = state.tree[medium][exam][subject][chapter] || [];
    renderOptions(els.topic, topics.filter(Boolean), 'Select Topic', true);
  });

  els.topic.on('change', function () {
    els.start.prop('disabled', !$(this).val());
  });

  els.start.on('click', function () {
    els.performance.prop('hidden', true).empty();
    els.status.text(JMT_CONFIG.messages.loading);

    const payload = {
      action: 'jmt_get_questions',
      nonce: JMT_CONFIG.nonce,
      medium: els.medium.val(),
      exam: els.exam.val(),
      subject: els.subject.val(),
      chapter: els.chapter.val(),
      topic: els.topic.val(),
    };

    $.post(JMT_CONFIG.ajaxUrl, payload)
      .done((res) => {
        if (!res.success || !res.data.questions.length) {
          els.status.text(JMT_CONFIG.messages.noQuestions);
          els.questions.empty();
          return;
        }

        state.questions = res.data.questions;
        state.currentIndex = 0;
        state.startedAt = Date.now();
        state.attempted = 0;
        state.correct = 0;
        state.wrong = 0;

        els.status.text('Questions loaded.');
        renderQuestion();
      })
      .fail(() => {
        els.status.text('Failed to load questions.');
      });
  });

  els.questions.on('click', '.jmt-option', function () {
    const button = $(this);
    const selected = button.data('option');
    const q = state.questions[state.currentIndex];
    const correct = q.correct;

    $('.jmt-option').prop('disabled', true);

    if (selected === correct) {
      button.addClass('is-correct');
      state.correct += 1;
    } else {
      button.addClass('is-wrong');
      $('.jmt-option[data-option="' + correct + '"]').addClass('is-correct');
      state.wrong += 1;
    }

    state.attempted += 1;

    const explanation = $('.jmt-explanation');
    let explanationHtml = '<p><strong>Explanation:</strong> ' + q.explanation + '</p>';
    if (q.link) {
      explanationHtml += '<p><a href="' + escapeHtml(q.link) + '" target="_blank" rel="noopener noreferrer">Learn More</a></p>';
    }
    explanation[0].innerHTML = explanationHtml;
    explanation.prop('hidden', false);
    maybeTypeset();

    const nextHtml = '<button class="button button-secondary jmt-next">Next Question</button>';
    explanation.append(nextHtml);
  });

  els.questions.on('click', '.jmt-next', function () {
    state.currentIndex += 1;
    renderQuestion();
  });

  loadCriteria();
})(jQuery);
