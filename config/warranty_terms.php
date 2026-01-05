<?php
/**
 * Configuração dos Termos de Garantia
 *
 * Este arquivo contém o texto padrão que será exibido na impressão das Ordens de Serviço.
 * Você pode editar este texto conforme as políticas da sua empresa.
 */

return [
    'title' => 'TERMOS DE GARANTIA',

    'clauses' => [
        [
            'number' => '1',
            'text' => 'A garantia do serviço prestado é de [PERIODO_GARANTIA], contados a partir da data de entrega do equipamento. Durante esse período, o cliente tem direito à revisão do serviço realizado sem custos adicionais, desde que o defeito seja relacionado ao reparo efetuado.'
        ],
        [
            'number' => '2',
            'text' => 'A garantia cobre exclusivamente o serviço executado e as peças substituídas durante o reparo. Caso seja constatado que o defeito está relacionado ao serviço prestado, o prazo para correção é de até 7 (sete) dias úteis a partir da data de abertura do chamado de garantia.'
        ],
        [
            'number' => '3',
            'text' => 'Equipamentos novos, devidamente lacrados, seguem a garantia estabelecida pelo fabricante. Para acioná-la, o cliente deve apresentar a nota fiscal de compra e seguir as orientações do fabricante quanto aos procedimentos necessários.'
        ],
        [
            'number' => '4',
            'text' => 'Para acionar a garantia, o cliente deve apresentar esta Ordem de Serviço juntamente com o equipamento. A garantia NÃO cobre: danos causados por mau uso, quedas, contato com líquidos, oxidação, alterações não autorizadas ou problemas não relacionados ao serviço originalmente executado.'
        ]
    ],

    'footer' => 'Esta garantia é válida somente mediante apresentação desta Ordem de Serviço.'
];
