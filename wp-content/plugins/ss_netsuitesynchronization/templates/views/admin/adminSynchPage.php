<div class="ssNetSuiteSynchronizationContainer">
	<div class="grid-12 selectButtonContainet">	
		<ul class="ssHeaderNawBlock">
			<li><span data-section-id="ssSynch" class="active"><?php echo __('Synchronizations'); ?></span></li>
			<li><span data-section-id="ssSettings"><?php echo __('Settings'); ?></span></li>
		</ul>
	</div>
	<div class="grid-12 ssSection ssContentButtonInner" id="ssSynch">
		<?php if (!empty($settings) && isset($settings['getSiteContent']) && !empty($settings['getSiteContent'])) { ?>
		<div class="processSynchronizations" style="display: none;">
		</div>
		<div class="grid-12 no-padding functionalsButton">
			<button class="ssNetSuiteButton startSynchronizationCategory"><?php echo __('Synchronizations Categories (Step 1)'); ?></button>
			<button class="ssNetSuiteButton startSynchronization"><?php echo __('Synchronizations Products (Step 2)'); ?></button>
			<?php if (!empty($categoryHtml)) { ?>
				<hr/>
				<?php echo $categoryHtml; ?>
			<?php } ?>
		</div>
		<?php } else { ?>
			<?php echo __('Before start please choose settings.'); ?>
		<?php } ?>
	</div>

	<div class="grid-12 ssSection ssContentButtonInner" id="ssSettings">
		<div class="grid-12 no-padding">
			<form action="" method="post">
				<div class="formRow">
					<label for=""><?php echo __('Select Site'); ?>:</label>
					<select name="getSiteContent" id="">
						<option value="2" <?php if (isset($settings['getSiteContent']) && $settings['getSiteContent'] == 2) echo 'selected'; ?> >www.topbookshop.com.au</option>
						<option value="1" <?php if (isset($settings['getSiteContent']) && $settings['getSiteContent'] == 1) echo 'selected'; ?>>www.ljharper.com.au</option>
					</select>
				</div>
				<!--
				<div class="formRow">
					<label for=""><?php echo __('Show up parent category?'); ?></label>
					<select name="showParentCategory" id="">
						<option value="1" <?php if (isset($settings['showParentCategory']) && $settings['showParentCategory'] == 1) echo 'selected'; ?> ><?php echo __('Yes'); ?></option>
						<option value="0" <?php if (isset($settings['showParentCategory']) && $settings['showParentCategory'] == 0) echo 'selected'; ?> ><?php echo __('No'); ?></option>
					</select>
				</div>
				-->
			    <?php 
			      
			    ?>
				<div class="formRow">
				    <label for=""><?php echo __('Select User'); ?>:</label>
				    <select name="user_name" class="user_name_box">
				    	<option value="-1">Select User</option>
				    	<option value="0">For every</option>
				        <?php foreach ($list_user as $list_key => $list){ ?>
				            <option value="<?php echo  $list->ID ?>"><?php echo $list->user_login;?></option>
				        <?php } ?>
				    </select>
				</div>
				<div class="formRow">
				    <?php echo $all_category; ?>
				</div>
				<div class="formRow">
					<button class="ssNetSuiteButton" type="submit"><?php echo __('Saved'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>