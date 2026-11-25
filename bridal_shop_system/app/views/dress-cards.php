<?php foreach(($items ?? $featured ?? []) as $dress): ?>
<article class="dress-card">
  <div class="dress-visual"><?php if(!empty($dress['photo_url'])): ?><img src="<?= e($dress['photo_url']) ?>" alt="<?= e($dress['name']) ?> wedding dress" loading="lazy"><?php else: ?><div class="photo-placeholder"><strong><?= e($dress['name']) ?></strong><span>Add a dress photo</span></div><?php endif; ?><span class="dress-size"><?= e($dress['size']) ?></span></div>
  <div class="dress-info"><div class="dress-meta"><?= e($dress['color']) ?> · Size <?= e($dress['size']) ?></div>
    <h3><?= e($dress['name']) ?></h3><p><?= e($dress['description'] ?? '') ?></p>
    <div class="card-bottom"><strong><?= number_format((float)$dress['rental_price']) ?> <small>RWF</small></strong>
    <?php if($dress['available']): ?><span class="available">Available to rent</span><?php else: ?><span class="unavailable">Rented</span><?php endif; ?></div>
    <div class="card-actions">
      <a class="button full" href="dress.php?id=<?= (int)$dress['dress_id'] ?>">View dress</a>
      <?php if(!empty($managerListing)): ?>
        <a class="button secondary" href="dresses.php?edit=<?= (int)$dress['dress_id'] ?>">Edit</a>
        <form method="post" action="dress-delete.php" onsubmit="return confirm('Delete this dress?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="dress_id" value="<?= (int)$dress['dress_id'] ?>"><button class="button danger" type="submit">Delete</button></form>
      <?php endif; ?>
    </div>
  </div>
</article>
<?php endforeach; ?>
