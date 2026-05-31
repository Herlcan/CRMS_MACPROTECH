<tr id="row-<?= $wo['id'] ?>">
	<td style="text-align: center;"><?= htmlspecialchars($wo['code']) ?></td>
	<td style="text-align: center;"><?= htmlspecialchars($wo['request_date']) ?></td>
	<td style="text-align: center;"><?= htmlspecialchars($wo['unit_type']) ?></td>
	<td style="text-align: center;"><?= htmlspecialchars($wo['brand']) . ' ' . $wo['model'] ?></td>
			
	<td style="text-align: center;"><?= htmlspecialchars($wo['prob_find']) ?></td>
			
	<td style="text-align: center;"><?= 'Php'.' '.htmlspecialchars($wo['work_order_cost'] + $wo['diagnostic_fee']) ?></td>
			
	<td style="text-align: center;"><?= htmlspecialchars($wo['completion_date'] ?? '—')?></td>
	
	<td style="text-align: center;">
	<?php
		$display_status = ($wo['status'] === 'Ready for Release') ? 'Repaired' : $wo['status'];
		$status = strtolower($display_status);
		$status_class = '';
		if ($status == 'pending') {
			$status_class = 'bg-pending';
		} elseif ($status == 'diagnosing') {
			$status_class = 'bg-diagnosing';
		} elseif ($status == 'waiting for parts') {
			$status_class = 'bg-waiting';
		} elseif ($status == 'in progress') {
			$status_class = 'bg-inprogress';
		} elseif ($status == 'repaired') {
			$status_class = 'bg-repaired';
		} elseif ($status == 'released') {
			$status_class = 'bg-released';
		}
		 elseif ($status == 'cancelled') {
			$status_class = 'bg-cancelled';
		}
	?>
	<?php if ($canEdit): ?>
		<div class="status-wrapper" style="position: relative; width:100%;">
			<select 
				class="form-select badge text-white <?= $status_class ?> status-select"
				data-id="<?= $wo['id'] ?>"
				data-old="<?= $display_status ?>"
				style="width: 100%; border: 0;">
				<option value="Pending" <?= ($display_status == 'Pending') ? 'selected' : '' ?>>Pending</option>
				<option value="Diagnosing" <?= ($display_status == 'Diagnosing') ? 'selected' : '' ?>>Diagnosing</option>
				<option value="Waiting for Parts" <?= ($display_status == 'Waiting for Parts') ? 'selected' : '' ?>>Waiting for Parts</option>
				<option value="In Progress" <?= ($display_status == 'In Progress') ? 'selected' : '' ?>>In Progress</option>
				<option value="Repaired" <?= ($display_status == 'Repaired') ? 'selected' : '' ?>>Repaired</option>
				<option value="Released" <?= ($display_status == 'Released') ? 'selected' : '' ?>>Released</option>
				<option value="Cancelled" <?= ($display_status == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
			</select>
			<div class="status-loading"></div>
		</div>
	<?php else: ?>
		<span class="badge <?= $status_class ?>" style="width: 100%;">
			<?= htmlspecialchars($display_status) ?>
		</span>
	<?php endif; ?>
	</td>
			
	<td style="text-align: center;">
		<button class="btn btn-sm btn-primary view-workorder-btn" data-id="<?= $wo['id'] ?>" style="margin-right: 5px;">
			<i class="dw dw-eye"></i> View
		</button>
	</td>
</tr>
