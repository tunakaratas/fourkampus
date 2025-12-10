<?php
namespace UniPanel\Core;

/**
 * Batch Processor
 * Büyük veri setlerini batch'ler halinde işler
 */
class BatchProcessor {
    
    /**
     * Array'i batch'lere böl
     */
    public static function chunk(array $items, $batchSize = 100) {
        return array_chunk($items, $batchSize);
    }
    
    /**
     * Batch'ler halinde işle
     */
    public static function processBatches(array $items, callable $processor, $batchSize = 100, $delay = 0) {
        $batches = self::chunk($items, $batchSize);
        $results = [];
        
        foreach ($batches as $batchIndex => $batch) {
            $batchResult = $processor($batch, $batchIndex);
            $results[] = $batchResult;
            
            // Memory temizliği
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            // Delay (rate limiting için)
            if ($delay > 0 && $batchIndex < count($batches) - 1) {
                usleep($delay * 1000); // milliseconds to microseconds
            }
        }
        
        return $results;
    }
    
    /**
     * Database batch insert
     */
    public static function batchInsert($db, $table, array $columns, array $rows, $batchSize = 100) {
        if (empty($rows)) {
            return 0;
        }
        
        $batches = self::chunk($rows, $batchSize);
        $totalInserted = 0;
        
        foreach ($batches as $batch) {
            $placeholders = [];
            $values = [];
            
            foreach ($batch as $row) {
                $rowPlaceholders = [];
                foreach ($columns as $col) {
                    $rowPlaceholders[] = '?';
                    $values[] = $row[$col] ?? null;
                }
                $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            }
            
            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $placeholders);
            $stmt = $db->prepare($sql);
            
            if ($stmt) {
                $paramIndex = 1;
                foreach ($values as $value) {
                    $stmt->bindValue($paramIndex++, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
                }
                
                if ($stmt->execute()) {
                    $totalInserted += count($batch);
                }
                $stmt->close();
            }
        }
        
        return $totalInserted;
    }
}

