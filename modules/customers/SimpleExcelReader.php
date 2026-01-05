<?php
/**
 * Leitor simples de arquivos Excel sem dependências externas
 * Suporta: CSV, XLSX (via XML)
 */

class SimpleExcelReader {

    public static function readFile($filePath, $originalFileName = null) {
        // Se temos o nome original do arquivo, usar ele para detectar extensão
        if ($originalFileName) {
            $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        } else {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        }

        // Se ainda não conseguiu detectar, tentar pelos primeiros bytes do arquivo
        if (empty($extension) || $extension === 'tmp') {
            $extension = self::detectarTipoArquivo($filePath);
        }

        switch ($extension) {
            case 'csv':
                return self::readCSV($filePath);
            case 'xlsx':
                return self::readXLSX($filePath);
            case 'xls':
                return self::readXLS($filePath);
            default:
                throw new Exception("Formato não suportado: $extension");
        }
    }

    private static function detectarTipoArquivo($filePath) {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return 'unknown';
        }

        $bytes = fread($handle, 8);
        fclose($handle);

        // Detectar por magic bytes (assinatura do arquivo)
        if (substr($bytes, 0, 2) === 'PK') {
            // ZIP signature (XLSX é um arquivo ZIP)
            return 'xlsx';
        } elseif (substr($bytes, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            // OLE2 signature (XLS formato antigo)
            return 'xls';
        }

        // Tentar como CSV por padrão
        return 'csv';
    }

    /**
     * Detecta automaticamente as colunas baseado no cabeçalho
     * Retorna um array de mapeamento: [indice_coluna => campo_banco]
     */
    public static function detectarColunas($linhaCabecalho) {
        $mapeamento = [];

        foreach ($linhaCabecalho as $indice => $nomeColuna) {
            $nomeColuna = strtolower(trim($nomeColuna));
            $nomeColuna = self::removerAcentos($nomeColuna);

            // Mapear colunas baseado em palavras-chave
            if (preg_match('/(nome|cliente|razao|gal)/i', $nomeColuna)) {
                $mapeamento[$indice] = 'name';
            } elseif (preg_match('/(cpf|cnpj|documento)/i', $nomeColuna)) {
                $mapeamento[$indice] = 'cpf_cnpj';
            } elseif (preg_match('/(telefone|fone|celular|contato)/i', $nomeColuna)) {
                $mapeamento[$indice] = 'phone';
            } elseif (preg_match('/(email|e-mail)/i', $nomeColuna)) {
                $mapeamento[$indice] = 'email';
            } elseif (preg_match('/(endereco|rua|logradouro)/i', $nomeColuna)) {
                $mapeamento[$indice] = 'address';
            } elseif (preg_match('/(cidade|municipio)/i', $nomeColuna)) {
                $mapeamento[$indice] = 'city';
            } elseif (preg_match('/(estado|uf)/i', $nomeColuna)) {
                $mapeamento[$indice] = 'state';
            } elseif (preg_match('/(cep|zip)/i', $nomeColuna)) {
                $mapeamento[$indice] = 'zipcode';
            } elseif (preg_match('/(obs|observ|nota|anotacao)/i', $nomeColuna)) {
                $mapeamento[$indice] = 'notes';
            }
        }

        return $mapeamento;
    }

    private static function removerAcentos($string) {
        $acentos = ['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ü','ç','Á','À','Ã','Â','É','Ê','Í','Ó','Ô','Õ','Ú','Ü','Ç'];
        $sem = ['a','a','a','a','e','e','i','o','o','o','u','u','c','A','A','A','A','E','E','I','O','O','O','U','U','C'];
        return str_replace($acentos, $sem, $string);
    }

    private static function readCSV($filePath) {
        $data = [];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new Exception("Não foi possível abrir o arquivo CSV");
        }

        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $data[] = $row;
        }

        fclose($handle);
        return $data;
    }

    private static function readXLSX($filePath) {
        // XLSX é um arquivo ZIP contendo XML
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== TRUE) {
            throw new Exception("Não foi possível abrir o arquivo XLSX");
        }

        // Ler o arquivo de strings compartilhadas
        $sharedStringsXML = $zip->getFromName('xl/sharedStrings.xml');
        $sharedStrings = [];

        if ($sharedStringsXML) {
            $xml = simplexml_load_string($sharedStringsXML);
            foreach ($xml->si as $si) {
                $sharedStrings[] = (string)$si->t;
            }
        }

        // Ler a primeira planilha
        $sheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXML) {
            throw new Exception("Não foi possível ler a planilha");
        }

        $xml = simplexml_load_string($sheetXML);
        $data = [];

        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $value = '';

                // Verificar se é string compartilhada
                if (isset($cell['t']) && (string)$cell['t'] === 's') {
                    $index = (int)$cell->v;
                    $value = $sharedStrings[$index] ?? '';
                } else {
                    $value = (string)$cell->v;
                }

                $rowData[] = $value;
            }
            $data[] = $rowData;
        }

        return $data;
    }

    private static function readXLS($filePath) {
        // Para .xls (formato antigo), vamos tentar ler como CSV com diferentes delimitadores
        // ou sugerir converter para XLSX

        // Tentar detectar se é um arquivo tab-separated
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception("Não foi possível abrir o arquivo XLS");
        }

        $data = [];

        // Tentar ler como TSV (tab-separated)
        while (($row = fgetcsv($handle, 1000, "\t")) !== FALSE) {
            $data[] = $row;
        }

        // Se falhar, tentar como CSV normal
        if (empty($data) || count($data[0]) <= 1) {
            rewind($handle);
            $data = [];
            while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $data[] = $row;
            }
        }

        fclose($handle);

        if (empty($data)) {
            throw new Exception("Por favor, converta o arquivo .xls para .xlsx ou .csv");
        }

        return $data;
    }
}
