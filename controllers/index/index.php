<?= Block::put('body') ?>
    <?php if (!$this->fatalError): ?>
        <div class="position-relative h-100" id="page-container">
            <editor-component-application :store="store" :custom-logo="'<?= e($customLogo) ?>'" ref="application">
            </editor-component-application>
        </div>

        <script id="editor-initial-state" type="text/template"><?= json_encode($initialState) ?></script>
    <?php else: ?>
        <p class="flash-message static error"><?= e(__($this->fatalError)) ?></p>
    <?php endif ?>
<?= Block::endPut() ?>
