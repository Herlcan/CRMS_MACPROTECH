<tr id="row-<?= $wo['id'] ?>">
	<td style="text-align: center;"><?= htmlspecialchars($wo['code']) ?></td>
	<td style="text-align: center;"><?= htmlspecialchars($wo['request_date']) ?></td>
	<td style="text-align: center;"><?= htmlspecialchars($wo['unit_type']) ?></td>
	<td style="text-align: center;"><?= htmlspecialchars($wo['brand']) . ' ' . $wo['model'] ?></td>
			
	<td style="text-align: center;"><?= htmlspecialchars($wo['prob_find']) ?></td>
			
	<td style="text-align: center;"><?= 'Php'.' '.htmlspecialchars($wo['work_order_cost']) ?></td>
			
	<td style="text-align: center;"><?= htmlspecialchars($wo['completion_date'] ?? '—')?></td>
	
	<td style="text-align: center;">
	<?php
		$status = strtolower($wo['status']);
		$status_class = '';
		if ($status == 'pending') {
			$status_class = 'bg-pending';
		} elseif ($status == 'in progress') {
			$status_class = 'bg-inprogress';
		} elseif ($status == 'completed') {
			$status_class = 'bg-completed';
		} elseif ($status == 'repaired') {
			$status_class = 'bg-repaired';
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
				data-old="<?= $wo['status'] ?>"
				style="width: 100%; border: 0;">
				<option value="Pending" <?= ($wo['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
				<option value="In Progress" <?= ($wo['status'] == 'In Progress') ? 'selected' : '' ?>>In Progress</option>
				<option value="Repaired" <?= ($wo['status'] == 'Repaired') ? 'selected' : '' ?>>Repaired</option>
				<option value="Cancelled" <?= ($wo['status'] == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
			</select>
			<div class="status-loading"></div>
		</div>
	<?php else: ?>
		<span class="badge <?= $status_class ?>" style="width: 100%;">
			<?= htmlspecialchars($wo['status']) ?>
		</span>
	<?php endif; ?>
	</td>
			
	<td style="text-align: center;">
		<div class="dropdown">
			<a class="btn btn-link font-24 p-0 line-height-1 no-arrow dropdown-toggle" href="#" role="button" data-toggle="dropdown">
				<img src="src/images/menu-dots.png" width="25px" style="border: none">
			</a>
			<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
				<a class="dropdown-item" href="#"><i class="dw dw-eye"></i> View</a>
				<a class="dropdown-item" href="#" data-toggle="modal" data-target="#delete"><i class="dw dw-delete-3"></i> Delete</a>
			</div>
		</div>
	</td>
</tr>
