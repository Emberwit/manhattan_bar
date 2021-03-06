<?php
$page_title='order';

include('header.php');
include('order_header.php');

$date = isset($_GET['date']) ? strtotime($_GET['date']) : time();
$soll = 0; $ist = 0;

if ($_POST) {
	$sql_pay = ""; $sql_unpay = "";
	foreach ($_POST as $key=>$value) {
		if ($value) { $sql_pay .= "OR id = $key "; }
		else { $sql_unpay .= "OR id = $key "; }
	}
	mysqli_query($db, "UPDATE orders SET paid = 1 WHERE FALSE $sql_pay");
	mysqli_query($db, "UPDATE orders SET paid = 0 WHERE FALSE $sql_unpay");
}

if (isset($_GET['delete'])) {
	mysqli_query($db, "UPDATE orders SET deleted = 1 WHERE id = " . $_GET['delete']);
}

$orders = mysqli_query($db,
"SELECT o.id, TIME(o.date) as time, o.slot, houses.name AS house_long, houses.shortname AS house, o.room, o.name, o.comment, o.paid,
	(CASE WHEN o.house = 1 THEN 0.5 ELSE 1 END) +
	SUM(price) as sum
FROM orders o
	LEFT JOIN (
		SELECT p.order_id, p.position, 4.00 +
			(CASE WHEN p.patty = 1 THEN 2 ELSE 0 END) +
			(CASE WHEN p.patty = 2 THEN 1.5 ELSE 0 END) +
			(CASE WHEN p.bacon = 1 THEN 0.5 ELSE 0 END) +
			(CASE WHEN p.camembert = 1 THEN 0.5 ELSE 0 END) +
			(CASE WHEN p.beilage = 0 THEN 0 ELSE 1.4 END) +
			(CASE WHEN p.dip_1 = 1 THEN 0.1 ELSE 0 END) +
			(CASE WHEN p.dip_2 = 1 THEN 0.1 ELSE 0 END) +
			(CASE WHEN p.bier = 0 THEN 0 ELSE
					(CASE WHEN p.bier = 10 OR p.bier = 11 THEN 2.5 ELSE 1.4 END)
			END) as price
		FROM menu_positions p
		) AS positions ON (o.id = order_id)
		LEFT JOIN houses ON o.house = houses.id
WHERE deleted = 0 AND DATE(o.date) = '" . date('Y-m-d', $date) . "' GROUP BY o.id ORDER BY o.id DESC");

?>
	<div class="content">
		<div class="card">
			<div class="card-title">

				<?php
					$date_previous_order = mysqli_fetch_row(mysqli_query($db, 'SELECT MAX(DATE(date)) FROM orders WHERE DATE(date) < "'.date('Y-m-d', $date).'" AND deleted = 0'))[0];
					$date_next_order = mysqli_fetch_row(mysqli_query($db, 'SELECT MAX(DATE(date)) FROM orders WHERE DATE(date) > "'.date('Y-m-d', $date).'" AND deleted = 0'))[0];
				?>

				<?php if($date_previous_order != ''){ ?>
					<a class="card-title-arrow-left" href="order_list.php?date=<?php echo $date_previous_order ?>">
						<i class='fa fa-chevron-left'></i>
					</a>
				<?php } else { ?>
					<a class="card-title-arrow-left"><i class='fa fa-chevron-left' style='color: rgb(1,1,1,0.15);'></i></a>
				<?php } ?>

				<?php echo(ucfirst($weekdays[date('w',$date)]).'., '.date('j. ', $date).$months[date('n', $date)-1].date(' Y', $date)); ?>

				<?php if($date_next_order != ''){ ?>
					<a class="card-title-arrow-right" href="order_list.php?date=<?php echo $date_next_order ?>">
						<i class='fa fa-chevron-right'></i>
					</a>
				<?php } else { ?>
					<a class="card-title-arrow-right"><i class='fa fa-chevron-right' style='color: rgb(1,1,1,0.15);'></i></a>
				<?php } ?>

			</div>
			<div class="card-content">
				<div class="progress-bar-wrapper">
					<?php
						// fetching data for progress bars
						$slot1_current = mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(slot) FROM orders WHERE deleted = 0 AND DATE(date) = '" . date('Y-m-d', $date) . "' AND slot = 0"))[0];
						$slot2_current = mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(slot) FROM orders WHERE deleted = 0 AND DATE(date) = '" . date('Y-m-d', $date) . "' AND slot = 1"))[0];
						$slot_max = $settings['order_max_slot'];

						$slot1_orders_by_house = mysqli_query($db, "SELECT houses.name AS house, count(house) AS num, color FROM orders LEFT JOIN houses ON orders.house = houses.id WHERE DATE(date) = '" . date('Y-m-d', $date) . "' AND slot = 0 AND deleted = 0 GROUP BY house ORDER BY num DESC");

						$slot2_orders_by_house = mysqli_query($db, "SELECT houses.name AS house, count(house) AS num, color FROM orders LEFT JOIN houses ON orders.house = houses.id WHERE DATE(date) = '" . date('Y-m-d', $date) . "' AND slot = 1 AND deleted = 0 GROUP BY house ORDER BY num DESC");
					?>

					<span class="progress-bar-label">Slot 1: <?php echo($slot1_current.' / '.$slot_max) ?></span>
					<div class="progress-bar">
						<?php foreach ($slot1_orders_by_house as $order) { ?><span class="progress" title="<?php echo($order['house'].': '.$order['num']) ?>" style="width: <?php echo($order['num'] / max($slot_max, $slot1_current) * 100) ?>%; background-color: <?php echo($order['color']) ?>;"></span><?php } ?>
					</div>
					<span class="progress-bar-label">Slot 2: <?php echo($slot2_current.' / '.$slot_max) ?></span>
					<div class="progress-bar">
						<?php foreach ($slot2_orders_by_house as $order) { ?><span class="progress" title="<?php echo($order['house'].': '.$order['num']) ?>" style="width: <?php echo($order['num'] / max($slot_max, $slot2_current) * 100) ?>%; background-color: <?php echo($order['color']) ?>;"></span><?php } ?>
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-title">
				Alle Bestellungen (<?php echo($slot1_current + $slot2_current); ?>)
			</div>
			<div class="card-content">
				<form method='post'>
					<table class="list" id="sortable_list">
						<tr>
							<th onclick="sortTable(0)"><i title="Bestellnummer" class="fa fa-hashtag"></i></th>
							<th onclick="sortTable(1)"><i title="Lieferslot" class="fa fa-clock-o"></i></th>
							<th onclick="sortTable(2)"><i title="Zimmer" class="fa fa-map-marker"></i></th>
							<th onclick="sortTable(3)"><i title="Name und Kommentar" class="fa fa-user"></i></th>
							<th onclick="sortTable(4)"><i title="Preis" class="fa fa-eur"></i></th>
							<th onclick="sortTable(5)"><i title="Bezahlt" class="fa fa-check-square-o"></i></th>
							<th></th>
						</tr>

						<?php foreach ($orders as $order) { ?>
							<tr>
								<td style="text-align: left; padding-left: 8px;">
									<div><?php echo $order['id']; ?></div>
									<div class="subtext"><?php echo date('H:i', strtotime($order['time'])); ?></div>
								</td>
								<td>
									<div><?php echo $order['slot']+1; ?></div>
									</td>
								<td style="text-align: right; width: 30px;" title="<?php echo $order['house_long'].', '.$order['room'] ?>">
									<div><?php echo (strlen($order['house']) > 4) ? substr($order['house'], 0, 3).'.' : $order['house']; ?></div>
									<div class="subtext"><?php echo (strlen($order['room']) > 6) ? substr($order['room'], 0, 4).'...' : $order['room']; ?></div>
								</td>
								<td style="text-align: left">
									<div><?php echo $order['name']; ?></div>
									<div class="subtext" title="<?php echo $order['comment']; ?>"><?php echo $order['comment']; ?></div>
								</td>
								<td style="text-align: right; width: 75px;">
									<div><?php echo $order['sum']; ?> €</div>
								</td>
								<td>
									<input type='hidden' name='<?php echo $order['id'] ?>' value='0'/>
									<input type='checkbox' name='<?php echo $order['id'] ?>' value='1' <?php echo ($order['paid'] ? "checked='checked'" : ""); ?> />
								</td>
								<td style="padding-right: 8px;">
									<a onclick="return confirm('Bestellung <?php echo $order['id'] ?> von <?php echo $order['name']; ?> wirklich löschen?');" href="order_list.php?date=<?php echo date('Y-m-d', $date) ?>&delete=<?php echo $order['id'] ?>">
										<div class="remove">
											<i class="fa fa-trash"></i>
										</div>
									</a>
								</td>
							</tr>
						<?php
							$soll += $order['sum'];
							$ist += $order['paid'] ? $order['sum'] : 0;
						} ?>
					</table>
					<p> <?php printf("eingeganene Zahlungen: %s€ (von %s€)", $ist, $soll); ?> </p>
					<input type='submit' value='Speichern'/>
				</form>
			</div>
		</div>
	</div>
</body>
</html>
