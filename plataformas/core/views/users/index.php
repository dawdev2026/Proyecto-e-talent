<section class="page-header">
    <div>
        <p class="text-uppercase text-primary fw-bold small mb-1">Administracion</p>
        <h1 class="fw-bold mb-1">Usuarios</h1>
        <p class="text-muted mb-0">Cuentas de administracion, operacion y usuarios asociados a empresas.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="<?= e(route_url('user.import')) ?>"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Carga masiva</a>
        <a class="btn btn-primary" href="<?= e(route_url('user.new')) ?>"><i class="bi bi-person-plus me-1"></i> Nuevo usuario</a>
    </div>
</section>

<section class="content-panel">
    <div class="table-responsive">
        <table
            class="table align-middle app-table app-data-table"
            data-export-title="Usuarios"
            data-server-url="<?= e(app_url('users/data')) ?>"
            data-page-length="25"
            data-export-excel="false"
            data-export-pdf="false"
        >
            <thead>
                <tr>
                    <th>RUT</th>
                    <th>Nombres</th>
                    <th>Apellidos</th>
                    <th>Correo</th>
                    <th>Sexo</th>
                    <th>Fecha nacimiento</th>
                    <th>Edad</th>
                    <th>Perfil</th>
                    <th>Empresa</th>
                    <?php foreach ($listFields as $field): ?>
                        <th><?= e($field['label']) ?></th>
                    <?php endforeach; ?>
                    <th>Estado</th>
                    <th class="text-end no-sort no-export">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</section>
