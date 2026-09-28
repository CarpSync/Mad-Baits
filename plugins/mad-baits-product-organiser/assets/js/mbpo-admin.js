(function ($) {
	'use strict';

	function showSortableError($list) {
		if (!$list.length || $list.prev('.mbpo-admin__sortable-error').length) {
			return;
		}
		$list.before(
			'<div class="notice notice-error mbpo-admin__sortable-error"><p>Drag-and-drop could not start (sortable library missing). Refresh the page or disable conflicting admin plugins.</p></div>'
		);
	}

	function initSortable() {
		var $list = $('#mbpo-product-order-list');
		if (!$list.length) {
			return;
		}

		if (!$.fn.sortable) {
			showSortableError($list);
			return;
		}

		$list.prev('.mbpo-admin__sortable-error').remove();

		if ($list.hasClass('ui-sortable')) {
			$list.sortable('destroy');
		}

		$list.sortable({
			axis: 'y',
			items: '> .mbpo-admin__item',
			cancel: 'a, button, input, select, textarea, label',
			placeholder: 'mbpo-admin__placeholder',
			forcePlaceholderSize: true,
			tolerance: 'pointer',
			cursor: 'grabbing',
			delay: 120,
			distance: 5,
			start: function (_event, ui) {
				ui.item.addClass('is-dragging');
			},
			stop: function (_event, ui) {
				ui.item.removeClass('is-dragging');
			},
		});
	}

	function initTagMultiselect() {
		var $select = $('#mbpo-bulk-tag-select');
		if (!$select.length) {
			return;
		}

		if ($.fn.selectWoo) {
			if ($select.hasClass('select2-hidden-accessible')) {
				$select.selectWoo('destroy');
			}
			$select.selectWoo({
				width: '420px',
				placeholder: $select.data('placeholder') || 'Choose tags…',
				allowClear: true,
				closeOnSelect: false,
			});
		}
	}

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function renderTagPreviewRows(rows) {
		var $body = $('#mbpo-tag-preview-body');
		if (!$body.length) {
			return;
		}

		if (!rows || !rows.length) {
			$body.html(
				'<tr><td colspan="5">No products match this filter.</td></tr>'
			);
			$('#mbpo-apply-clean-tags').prop('disabled', true);
			$('#mbpo-tag-select-all').prop('checked', false);
			return;
		}

		var html = rows
			.map(function (row) {
				return (
					'<tr data-product-id="' +
					escapeHtml(row.id) +
					'">' +
					'<th scope="row" class="check-column">' +
					'<input type="checkbox" class="mbpo-tag-product-cb" value="' +
					escapeHtml(row.id) +
					'" />' +
					'</th>' +
					'<td><strong>' +
					escapeHtml(row.name) +
					'</strong> <span class="mbpo-admin__id">#' +
					escapeHtml(row.id) +
					'</span></td>' +
					'<td>' +
					escapeHtml((row.current || []).join(', ')) +
					'</td>' +
					'<td class="mbpo-admin__tag-remove">' +
					escapeHtml((row.remove || []).join(', ')) +
					'</td>' +
					'<td>' +
					escapeHtml((row.keep || []).join(', ')) +
					'</td>' +
					'</tr>'
				);
			})
			.join('');

		$body.html(html);
		$('#mbpo-apply-clean-tags').prop('disabled', false);
		$('#mbpo-tag-select-all').prop('checked', false);
	}

	function getSelectedProductIds() {
		return $('.mbpo-tag-product-cb:checked')
			.map(function () {
				return parseInt($(this).val(), 10);
			})
			.get()
			.filter(function (id) {
				return id > 0;
			});
	}

	function getSelectedTagIds() {
		var $select = $('#mbpo-bulk-tag-select');
		var val = $select.val();
		if (!val) {
			return [];
		}
		return (Array.isArray(val) ? val : [val])
			.map(function (id) {
				return parseInt(id, 10);
			})
			.filter(function (id) {
				return id > 0;
			});
	}

	$(function () {
		$('#mbpo-range-select').on('change', function () {
			$(this).closest('form').trigger('submit');
		});

		initSortable();
		$(window).on('load', initSortable);
		initTagMultiselect();

		$(document).on('change', '#mbpo-tag-select-all', function () {
			var checked = $(this).is(':checked');
			$('.mbpo-tag-product-cb').prop('checked', checked);
		});

		$(document).on('change', '.mbpo-tag-product-cb', function () {
			var $all = $('.mbpo-tag-product-cb');
			var $checked = $all.filter(':checked');
			$('#mbpo-tag-select-all').prop(
				'checked',
				$all.length > 0 && $checked.length === $all.length
			);
		});

		$('#mbpo-refresh-tag-preview').on('click', function () {
			var $btn = $(this);
			var $status = $('#mbpo-tag-cleanup-status');
			var config = window.mbpoAdmin || {};
			var showAll = $('#mbpo-tag-show-all').is(':checked');

			$btn.prop('disabled', true);
			$status.text('Loading preview…');

			$.post(config.ajaxUrl || ajaxurl, {
				action: 'mbpo_preview_clean_tags',
				nonce: $btn.data('nonce'),
				show_all: showAll ? 1 : 0,
			})
				.done(function (response) {
					if (response && response.success) {
						renderTagPreviewRows(response.data && response.data.rows);
						$status.text(
							(response.data && response.data.count) +
								' products listed.'
						);
					} else {
						$status.text('Preview failed.');
					}
				})
				.fail(function () {
					$status.text('Preview failed. Try again.');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});

		$('#mbpo-bulk-assign-tags').on('click', function () {
			var $btn = $(this);
			var $status = $('#mbpo-tag-cleanup-status');
			var config = window.mbpoAdmin || {};
			var productIds = getSelectedProductIds();
			var tagIds = getSelectedTagIds();

			if (!productIds.length) {
				$status.text(config.i18nSelectProducts || 'Select at least one product.');
				return;
			}

			if (!tagIds.length) {
				$status.text(config.i18nSelectTags || 'Select at least one tag.');
				return;
			}

			if (!window.confirm(config.i18nConfirmBulkAssign || 'Assign selected tags?')) {
				return;
			}

			$btn.prop('disabled', true);
			$status.text(config.i18nAssigningTags || 'Assigning tags…');

			$.post(config.ajaxUrl || ajaxurl, {
				action: 'mbpo_bulk_assign_tags',
				nonce: $btn.data('nonce'),
				product_ids: productIds,
				tag_ids: tagIds,
			})
				.done(function (response) {
					if (response && response.success) {
						$status.text((response.data && response.data.message) || 'Done.');
						window.location.reload();
					} else {
						$status.text(
							(response && response.data && response.data.message) || 'Failed.'
						);
					}
				})
				.fail(function () {
					$status.text('Failed. Try again.');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});

		$('#mbpo-save-order').on('click', function () {
			var $btn = $(this);
			var $list = $('#mbpo-product-order-list');
			var $status = $('#mbpo-save-status');
			var config = window.mbpoAdmin || {};
			var ids = $list
				.children('.mbpo-admin__item')
				.map(function () {
					return $(this).data('product-id');
				})
				.get();

			$btn.prop('disabled', true);
			$status.text(config.i18nSaving || 'Saving…');

			$.post(config.ajaxUrl || ajaxurl, {
				action: 'mbpo_save_range_product_order',
				nonce: $list.data('nonce'),
				range_slug: $list.data('range'),
				product_ids: ids,
			})
				.done(function (response) {
					if (response && response.success) {
						$status.text((response.data && response.data.message) || config.i18nSaved || 'Saved.');
					} else {
						$status.text((response && response.data && response.data.message) || config.i18nSaveFailed || 'Save failed.');
					}
				})
				.fail(function () {
					$status.text(config.i18nSaveFailedRetry || 'Save failed. Try again.');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});

		$('#mbpo-rebuild-sections').on('click', function () {
			var $btn = $(this);
			var $status = $('#mbpo-recalc-status');
			var config = window.mbpoAdmin || {};

			$btn.prop('disabled', true);
			$('#mbpo-recalc-types').prop('disabled', true);
			$status.text(config.i18nRebuilding || 'Rebuilding range sections…');

			$.post(config.ajaxUrl || ajaxurl, {
				action: 'mbpo_rebuild_range_sections',
				nonce: $btn.data('nonce'),
			})
				.done(function (response) {
					if (response && response.success) {
						$status.text((response.data && response.data.message) || 'Done.');
						window.location.reload();
					} else {
						$status.text((response && response.data && response.data.message) || 'Failed.');
					}
				})
				.fail(function () {
					$status.text('Failed. Try again.');
				})
				.always(function () {
					$btn.prop('disabled', false);
					$('#mbpo-recalc-types').prop('disabled', false);
				});
		});

		$('#mbpo-apply-clean-tags').on('click', function () {
			var $btn = $(this);
			var $status = $('#mbpo-tag-cleanup-status');
			var config = window.mbpoAdmin || {};

			if (!window.confirm(config.i18nConfirmCleanTags || 'Apply tag cleanup?')) {
				return;
			}

			$btn.prop('disabled', true);
			$status.text(config.i18nApplyingTags || 'Cleaning product tags…');

			$.post(config.ajaxUrl || ajaxurl, {
				action: 'mbpo_apply_clean_tags',
				nonce: $btn.data('nonce'),
			})
				.done(function (response) {
					if (response && response.success) {
						$status.text((response.data && response.data.message) || 'Done.');
						window.location.reload();
					} else {
						$status.text((response && response.data && response.data.message) || 'Failed.');
					}
				})
				.fail(function () {
					$status.text('Failed. Try again.');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});

		$('#mbpo-recalc-types').on('click', function () {
			var $btn = $(this);
			var $status = $('#mbpo-recalc-status');
			var config = window.mbpoAdmin || {};

			$btn.prop('disabled', true);
			$status.text(config.i18nRecalculating || 'Recalculating…');

			$.post(config.ajaxUrl || ajaxurl, {
				action: 'mbpo_recalc_product_types',
				nonce: $btn.data('nonce'),
			})
				.done(function (response) {
					if (response && response.success) {
						$status.text((response.data && response.data.message) || 'Done.');
						window.location.reload();
					} else {
						$status.text((response && response.data && response.data.message) || 'Failed.');
					}
				})
				.fail(function () {
					$status.text('Failed. Try again.');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	});
})(jQuery);
