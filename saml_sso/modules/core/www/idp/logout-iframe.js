function updateStatus() {

	var nFailed = 0;
	var nProgress = 0;
	for (sp in window.spStatus) {
		switch (window.spStatus[sp]) {
		case 'failed':
			nFailed += 1;
			break;
		case 'inprogress':
			nProgress += 1;
			break;
		}
	}

	if (nFailed > 0) {
		$('#logout-failed-message').show();
	}

	if (nProgress == 0 && nFailed == 0) {
		$('#logout-completed').show();
		$('#done-form').submit();
	}
}

function updateSPStatus(spId, status, reason) {
	if (window.spStatus[spId] == status) {
		/* Unchanged. */
		return;
	}

	$('#statusimage-' + spId).attr('src', window.stateImage[status]).attr('alt', window.stateText[status]).attr('title', reason);
	window.spStatus[spId] = status;

	var formId = 'logout-iframe-' + spId;
	var existing = $('input[name="' + formId + '"]');
	if (existing.length == 0) {
		/* Don't have an existing form element - add one. */
		var elementHTML = '<input type="hidden" name="' + formId + '" value="' + status + '" />';
		$('#failed-form , #done-form').append(elementHTML);
	} else {
		/* Update existing element. */
		existing.attr('value', status);
	}

	updateStatus();
}
function logoutCompleted(spId) {
	updateSPStatus(spId, 'completed', '');
}
function logoutFailed(spId, reason) {
	updateSPStatus(spId, 'failed', reason);
}

function timeoutSPs() {
	var cTime = ( (new Date()).getTime() - window.startTime ) / 1000;
	for (sp in window.spStatus) {
		if (window.spTimeout[sp] <= cTime && window.spStatus[sp] == 'inprogress') {
			logoutFailed(sp, 'Timeout');
		}
	}
	window.timeoutID = window.setTimeout(timeoutSPs, 1000);
}

$('document').ready(function(){
	window.startTime = (new Date()).getTime();
	if (window.type == 'js') {
		window.timeoutID = window.setTimeout(timeoutSPs, 1000);
		updateStatus();
	} else if (window.type == 'init') {
		$('#logout-type-selector').attr('value', 'js');
		$('#logout-all').focus();
	}
});
