<?php if ( empty( $files ) ) : ?>
	<?php _e( 'No files in this folder', 'dfc_plugin' ); ?>
<?php else : ?>
	<ul class="dfc-file-listing">
	<?php foreach( $files['contents'] as $file ) : ?>
		<li class="<?php echo $file['icon']; ?>">
			<?php if ( $file['is_dir'] ) : ?>
				<a href="<?php echo str_replace( '?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI'] ) . basename( $file['path'] ); ?>"><?php echo basename( $file['path'] ) ;?></a>
			<?php else : ?>
				<a href="#"><?php echo basename( $file['path'] ) ;?></a> (<?php echo $file['size']; ?>)
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
	</ul>
<?php endif; ?>
