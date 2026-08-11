<?php
$code = (string) ($instrument['code'] ?? '');

$guides = [
    'ipip_16pf' => [
        'source' => 'IPIP-16PF cargado desde 16PF PREGUNTAS V2.PDF y 16 PF VERIFICADOR.xlsx.',
        'scaleIntro' => 'Este test trabaja con escalas primarias tipo 16PF, indicadores de control y dimensiones globales calculadas por formula.',
        'scaleRows' => [
            ['a, b, c, e...', 'Escalas primarias', 'Acumulan puntajes de respuestas asociadas a cada factor.'],
            ['mi, in, aq', 'Indicadores de control', 'Revisan patrones de respuesta como imagen, infrecuencia o aquiescencia.'],
            ['ext, ans, dur, ind, auc', 'Dimensiones globales', 'Se calculan desde otras escalas mediante formulas configuradas por test.'],
        ],
        'itemIntro' => 'Las preguntas son de seleccion unica. Cada enunciado usa TinyMCE y puede incluir texto enriquecido o imagenes dentro del mismo cuadro.',
        'itemRows' => [
            ['Tipo', 'Seleccion unica'],
            ['Alternativas', '1, 2 y 3; normalmente corresponden a dos polos y una opcion intermedia.'],
            ['Respuesta guardada', 'Un unico valor interno, por ejemplo 1, 2 o 3.'],
        ],
        'optionRows' => [
            ['1', 'Primer polo de respuesta'],
            ['2', '? / opcion intermedia'],
            ['3', 'Segundo polo de respuesta'],
        ],
        'scoringIntro' => 'Las valorizaciones se cargan por pregunta y escala. El puntaje bruto se transforma luego con los baremos del propio IPIP-16PF.',
        'scoringRows' => [
            ['Directa', '{"subtract":1}', 'Con alternativas 1/2/3 transforma 1->0, 2->1, 3->2.'],
            ['Inversa', '{"anchor":3}', 'Invierte el sentido: 1->2, 2->1, 3->0.'],
            ['Formula', 'test_scale_formula_terms', 'Combina escalas para generar dimensiones globales.'],
        ],
        'exampleTitle' => 'Ejemplo IPIP-16PF',
        'flow' => ['Escala<br><code>a</code>', 'Pregunta<br><code>q001</code>', 'Respuesta<br><code>3</code>', 'Regla directa<br><code>{"subtract":1}</code>', 'Resultado<br><code>+2 en a</code>'],
        'expected' => 'Si el usuario responde la alternativa con valor 3 en una pregunta directa asociada a la escala a, el puntaje bruto de a aumenta en 2. Luego el baremo del test puede transformar ese bruto a decatipo.',
    ],
    'cag_wonderlic' => [
        'source' => 'CAG cargado desde Wonderlic Preguntas.doc y Wonderlic Validacion.xls.',
        'scaleIntro' => 'Este test usa una escala principal de aptitud cognitiva general. El puntaje bruto es la cantidad de respuestas correctas.',
        'scaleRows' => [
            ['general_cognitive_ability', 'Aptitud cognitiva general', 'Suma 1 punto por cada respuesta correcta, con maximo base de 50.'],
            ['Ajuste por edad', 'test_score_adjustments', 'Lee el dato core edad y suma el ajuste configurado para el rango correspondiente.'],
            ['Baremos', 'test_norms', 'Usan score_source adjusted para buscar percentil e interpretacion sobre puntaje ajustado.'],
        ],
        'itemIntro' => 'Las preguntas mezclan seleccion unica, seleccion multiple, texto abierto y numerico. Las preguntas con figuras quedaron marcadas como pendientes de digitalizacion.',
        'itemRows' => [
            ['Tipo seleccion unica', 'Alternativas cerradas, por ejemplo pregunta 1: Diciembre es valor 4.'],
            ['Tipo numerico', 'Permite decimales con step any; sirve para respuestas como 6000, 18 o 24.'],
            ['Tipo texto abierto', 'Permite respuestas como 1/8, 1/4 seg, 6 y 9 o 3 y 22.'],
            ['Tipo seleccion multiple', 'Requiere combinacion completa, por ejemplo 2,5 o 1,2,4,5.'],
        ],
        'optionRows' => [
            ['4', 'Diciembre, respuesta correcta de q001'],
            ['si/no', 'Alternativas booleanas normalizadas para respuestas como SI o sí'],
            ['1,2,4,5', 'Combinacion completa para pregunta de figuras q049'],
        ],
        'scoringIntro' => 'Cada pregunta correcta suma 1. Las respuestas incorrectas o incompletas suman 0. Despues se aplica ajuste por edad y baremo por percentil.',
        'scoringRows' => [
            ['Mapa por clave', '4=1', 'Si en q001 responde 4, suma 1 punto.'],
            ['Texto normalizado', '0.125=1;1/8=1', 'Acepta formatos equivalentes para una misma respuesta.'],
            ['Ajuste contextual', 'edad 40-49 -> +2', 'Si el usuario tiene 42 anos, raw 50 pasa a adjusted 52.'],
            ['Baremo ajustado', 'score_source adjusted', 'Busca percentil y rango usando el puntaje ajustado.'],
        ],
        'exampleTitle' => 'Ejemplo CAG/Wonderlic',
        'flow' => ['Escala<br><code>general_cognitive_ability</code>', 'Pregunta<br><code>q001</code>', 'Respuesta<br><code>4</code>', 'Clave<br><code>4=1</code>', 'Resultado<br><code>+1 bruto</code>'],
        'expected' => 'Si un usuario de 42 anos obtiene 50 respuestas correctas, el sistema calcula bruto 50, aplica ajuste de edad +2, deja puntaje ajustado 52 y lo clasifica en el tramo Superior con percentil 100.',
    ],
    'ticl_barratt' => [
        'source' => 'TICL cargado desde Barrat Preguntas.pdf y correccion barratt.pdf; la fuente operacional es BIS-11/Barratt.',
        'scaleIntro' => 'Este test tiene 3 subescalas BIS-11 y una escala total calculada como suma de las tres.',
        'scaleRows' => [
            ['cognitive_impulsiveness', 'Impulsividad cognitiva', 'Items 4, 7, 10, 13, 16, 19, 24 y 27.'],
            ['motor_impulsiveness', 'Impulsividad motora', 'Items 2, 6, 9, 12, 15, 18, 21, 23, 26 y 29.'],
            ['non_planning', 'Impulsividad no planificada', 'Items 1, 3, 5, 8, 11, 14, 17, 20, 22, 25, 28 y 30.'],
            ['total_impulsiveness', 'Impulsividad total', 'Formula: suma de las tres subescalas.'],
        ],
        'itemIntro' => 'Las 30 preguntas son Likert de frecuencia. Algunas estan redactadas en sentido inverso y por eso su puntaje se invierte.',
        'itemRows' => [
            ['Tipo', 'Likert'],
            ['Alternativas', '1=Raramente o nunca; 2=Ocasionalmente; 3=A menudo; 4=Siempre o casi siempre.'],
            ['Items inversos', '1, 5, 6, 7, 8, 10, 11, 13, 17, 19, 22 y 30.'],
            ['Resultado esperado', 'A mayor puntaje, mayor impulsividad relativa. No hay punto de corte clinico propuesto.'],
        ],
        'optionRows' => [
            ['1', 'Raramente o nunca'],
            ['2', 'Ocasionalmente'],
            ['3', 'A menudo'],
            ['4', 'Siempre o casi siempre'],
        ],
        'scoringIntro' => 'La valorizacion depende de si el item es directo o inverso. Las medianas se cargan solo como referencia interpretativa.',
        'scoringRows' => [
            ['Directa', '1=1;2=2;3=3;4=4', 'Ejemplo q002: responder 4 suma 4 en impulsividad motora.'],
            ['Inversa', '1=4;2=3;3=2;4=1', 'Ejemplo q001: responder 1 suma 4 en no planificacion; responder 4 suma 1.'],
            ['Formula total', 'cognitive + motor + non_planning', 'Genera total_impulsiveness desde las tres subescalas.'],
            ['Referencia', 'Medianas 9.5, 9.5, 14 y 32.5', 'Se usan como referencia, no como corte diagnostico.'],
        ],
        'exampleTitle' => 'Ejemplo TICL/Barratt',
        'flow' => ['Escala<br><code>non_planning</code>', 'Pregunta<br><code>q001</code>', 'Respuesta<br><code>1</code>', 'Regla inversa<br><code>1=4</code>', 'Resultado<br><code>+4</code>'],
        'expected' => 'Si el usuario responde 1 en q001, al ser un item inverso suma 4 en impulsividad no planificada. Si responde 4, suma 1. El total se recalcula sumando cognitiva, motora y no planificada.',
    ],
];

$guide = $guides[$code] ?? [
    'source' => 'Configuracion general del test.',
    'scaleIntro' => 'La escala es el destino del puntaje. Cada regla de valorizacion suma puntos a una escala especifica.',
    'scaleRows' => [
        ['Clave', 'Identificador corto', 'Ejemplo: general, escala_a o total.'],
        ['Tipo primaria', 'Suma directa', 'Acumula reglas por item.'],
        ['Tipo global', 'Formula', 'Combina otras escalas.'],
    ],
    'itemIntro' => 'La pregunta contiene el enunciado que respondera el evaluado. El enunciado debe editarse con TinyMCE.',
    'itemRows' => [
        ['Likert', 'Escalas ordinales de frecuencia o acuerdo.'],
        ['Seleccion unica', 'Una sola alternativa.'],
        ['Seleccion multiple', 'Combinaciones o varias marcas.'],
        ['Texto/numerico', 'Respuesta escrita o valor directo.'],
    ],
    'optionRows' => [
        ['valor', 'Texto visible para el evaluado'],
    ],
    'scoringIntro' => 'La valorizacion indica como una respuesta se transforma en puntaje para una escala.',
    'scoringRows' => [
        ['Directa', '{"subtract":1}', 'Mantiene el sentido de la respuesta.'],
        ['Inversa', '{"anchor":3}', 'Invierte el sentido.'],
        ['Clave/mapa', 'respuesta=puntaje', 'Suma cuando coincide la respuesta.'],
    ],
    'exampleTitle' => 'Ejemplo del test',
    'flow' => ['Escala', 'Pregunta', 'Respuesta', 'Regla', 'Resultado'],
    'expected' => 'Resultado esperado: cada regla activa produce un puntaje para una escala; luego los baremos o formulas del test completan el resultado.',
];
?>

<section class="drawer-help">
    <div class="drawer-detail-heading">
        <p class="text-muted mb-0">Esta guia corresponde al test <strong><?= e($instrument['name']) ?></strong>. <?= e($guide['source']) ?></p>
    </div>

    <div class="help-section">
        <h3>1. Escalas</h3>
        <p><?= e($guide['scaleIntro']) ?></p>
        <div class="help-example-table">
            <div><strong>Clave</strong><span>Uso en este test</span></div>
            <?php foreach ($guide['scaleRows'] as $row): ?>
                <div><code><?= e($row[0]) ?></code><span><strong><?= e($row[1]) ?>:</strong> <?= e($row[2]) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="help-section">
        <h3>2. Preguntas</h3>
        <p><?= e($guide['itemIntro']) ?></p>
        <dl>
            <dt>Clave</dt>
            <dd>Identificador de la pregunta dentro del test. Ejemplo: <code>q001</code>.</dd>
            <dt>Enunciado</dt>
            <dd>Texto enriquecido editado con TinyMCE. Puede incluir texto e imagenes insertadas dentro del mismo campo.</dd>
            <dt>Tipo</dt>
            <dd>Define que control vera el usuario y como se guardara su respuesta.</dd>
            <dt>Activo</dt>
            <dd>Permite ocultar una pregunta sin eliminar su configuracion.</dd>
        </dl>
        <div class="help-example-table">
            <div><strong>Campo</strong><span>Configuracion esperada</span></div>
            <?php foreach ($guide['itemRows'] as $row): ?>
                <div><code><?= e($row[0]) ?></code><span><?= e($row[1]) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="help-section">
        <h3>3. Alternativas</h3>
        <p>Son las respuestas visibles para el usuario. Cada alternativa tiene un valor interno y un texto visible.</p>
        <div class="help-example-table">
            <div><strong>Valor</strong><span>Texto/uso visible</span></div>
            <?php foreach ($guide['optionRows'] as $row): ?>
                <div><code><?= e($row[0]) ?></code><span><?= e($row[1]) ?></span></div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted mb-0">El valor es lo que lee la regla de scoring; el texto visible es lo que ve el evaluado.</p>
    </div>

    <div class="help-section">
        <h3>4. Valorizaciones</h3>
        <p><?= e($guide['scoringIntro']) ?></p>
        <dl>
            <dt>Escala</dt>
            <dd>Destino del puntaje calculado. Debe pertenecer al mismo test.</dd>
            <dt>Tipo</dt>
            <dd>Define la forma de convertir una respuesta en puntaje: directa, inversa, clave o mapa.</dd>
            <dt>Respuesta</dt>
            <dd>Valor que debe coincidir cuando se usa regla de tipo clave o mapa.</dd>
            <dt>Puntaje</dt>
            <dd>Valor que se suma cuando la regla aplica.</dd>
            <dt>Peso</dt>
            <dd>Multiplica el puntaje final. Normalmente <code>1</code>.</dd>
            <dt>Config</dt>
            <dd>JSON usado solo cuando el tipo de regla lo requiere. Si el test usa <code>scoring_key</code> por item, puede quedar vacio en el mantenedor de reglas.</dd>
        </dl>
        <div class="help-example-table">
            <div><strong>Regla</strong><span>Resultado en este test</span></div>
            <?php foreach ($guide['scoringRows'] as $row): ?>
                <div><code><?= e($row[0]) ?></code><span><code><?= e($row[1]) ?></code> <?= e($row[2]) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="help-section help-result">
        <h3><?= e($guide['exampleTitle']) ?></h3>
        <p>Configuracion de ejemplo y resultado esperado:</p>
        <div class="test-config-flow" aria-label="Ejemplo de resultado esperado">
            <?php foreach ($guide['flow'] as $index => $step): ?>
                <span><?= $step ?></span>
                <?php if ($index < count($guide['flow']) - 1): ?>
                    <i class="bi bi-arrow-right"></i>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="mb-0"><?= e($guide['expected']) ?></p>
    </div>
</section>
