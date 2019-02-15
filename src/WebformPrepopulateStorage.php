<?php

namespace Drupal\webform_prepopulate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Driver\sqlite\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;

/**
 * Class WebformPrepopulateStorage.
 *
 * @todo refactor and allow other inputs (XLS, ...).
 */
class WebformPrepopulateStorage {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Database\Connection definition.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * CSV delimiter.
   *
   * @var string
   */
  private $delimiter;

  /**
   * Constructs a new WebformPrepopulateStorage object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection) {
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    $this->delimiter = ',';
  }

  /**
   * Returns the CSV delimiter.
   *
   * @return string
   */
  public function getDelimiter() {
    return $this->delimiter;
  }

  /**
   * Sets the CSV delimiter.
   *
   * @param string $delimiter
   */
  public function setDelimiter($delimiter) {
    $this->delimiter = $delimiter;
  }

  /**
   * Deletes entries from the database for a Webform.
   *
   * @param string $webform_id
   *
   * @return bool
   */
  public function deleteWebformData($webform_id) {
    $result = TRUE;
    try {
      $this->connection->delete('webform_prepopulate')
        ->condition('webform_id', $webform_id)->execute();
    }
    catch (\Throwable $exception) {
      $result = FALSE;
      \Drupal::messenger()->addError($exception->getMessage());
    }
    return $result;
  }

  /**
   * Checks if the file header has valid Webform element keys.
   *
   * Currently just assuming that an single intersection of
   * header keys and form elements keys is valid.
   *
   * @todo additionally, checks if the datatype is valid.
   *
   * @param string $webform_id
   * @param \Drupal\file\Entity\File $file
   *
   * @return bool
   */
  public function validateWebformSchema($webform_id, File $file) {
    return !empty($this->getWebformKeysFromFile($webform_id, $file));
  }

  /**
   * Gets the intersection between header keys and form elements keys.
   *
   * @param string $webform_id
   * @param \Drupal\file\Entity\File $file
   *
   * @return array
   */
  public function getWebformKeysFromFile($webform_id, File $file) {
    $result = [];
    try {
      /** @var \Drupal\webform\WebformEntityStorage $webformStorage */
      $webformStorage = $this->entityTypeManager->getStorage('webform');
      /** @var \Drupal\webform\Entity\Webform $webform */
      $webform = $webformStorage->load($webform_id);
      $header = $this->getFileHeader($file);
      $webformElements = array_keys($webform->getElementsDecoded());
      $result = array_intersect($header, $webformElements);
    }
    catch (\Throwable $exception) {
      \Drupal::messenger()->addError($exception->getMessage());
    }
    return $result;
  }

  /**
   * Returns the file header.
   *
   * Header is interpreted here as the first row.
   *
   * @param \Drupal\file\Entity\File $file
   *
   * @return array
   */
  public function getFileHeader(File $file) {
    $result = [];
    /** @var \Generator $generator */
    $generator = $this->readFileByLines($file, 1);
    if($headerLine = $generator->current()){
       $result = explode($this->getDelimiter(), $headerLine);
    }
    return $result;
  }

  /**
   * Reads a file by line using a generator.
   *
   * @param \Drupal\file\Entity\File $file
   * @param int $limit
   *
   * @return \Generator
   */
  private function readFileByLines(File $file, $limit = 0) {
    if (($handle = fopen($file->getFileUri(), 'rb')) !== FALSE) {
      if ($limit === 0) {
        while(($line = fgets($handle)) !== FALSE) {
          yield rtrim($line, "\r\n");
        }
      }
      else {
        $countLine = 0;
        while(($line = fgets($handle)) !== FALSE && $countLine < $limit) {
          yield rtrim($line, "\r\n");
          ++$countLine;
        }
      }
      fclose($handle);
    }
  }

  /**
   * Saves a file into the database.
   *
   * @param string $webform_id
   * @param \Drupal\file\Entity\File $file
   *
   * @return bool
   */
  private function saveFileData($webform_id, File $file) {
    // @todo define a strategy for small / big files with yield, batch, multiple inserts.
    $header = $this->getFileHeader($file);
    // @todo exception for several columns.
    $hashColumn = array_search('hash', array_map('strtolower', $header));

    // Fail if there is no hash column.
    if (!is_int($hashColumn)) {
      \Drupal::messenger()->addError($this->t('Your file should have a <em>hash</em> column.'));
      return FALSE;
    }

    // Remove any existing data.
    $this->deleteWebformData($webform_id);

    // Serialize with column keys / values.
    $inserted = 0;
    $lines = 0;
    foreach($this->readFileByLines($file) as $line) {
      // @todo exclude header in a more elegant way.
      if($lines > 0) {
        $lineValues = explode($this->getDelimiter(), $line);
        $hash = $lineValues[$hashColumn];
        // Remove the hash from the column and values
        // it does not need to be stored with the other serialized values.
        unset($header[$hashColumn]);
        unset($lineValues[$hashColumn]);
        // Remove then the keys before re-indexing.
        $indexedLine = $this->indexLineByColumns(array_values($header), array_values($lineValues));
        try {
          if(
            $this->connection->insert('webform_prepopulate')->fields([
              'webform_id' => $webform_id,
              'hash' => $hash,
              'data' => serialize($indexedLine),
              'timestamp' => \Drupal::time()->getRequestTime(),
            ])->execute()
          ) {
            ++$inserted;
          }
        }
        catch (\Throwable $exception) {
          // This will allow to fail early in case of duplicate hash.
          \Drupal::messenger()->addError($exception->getMessage());
          return FALSE;
        }
      }
      ++$lines;
    }
    // Remove the header from the comparison.
    return $inserted === $lines - 1;
  }

  /**
   * Indexes the line values before serialization.
   *
   * @param array $header
   * @param array $line_values
   *
   * @return array
   */
  private function indexLineByColumns(array $header, array $line_values) {
    $result = [];
    // Check if there is a mismatch between header and columns.
    if (count($header) !== count($line_values)) {
      // Make then a slower key / value assignment.
      $count = 0;
      foreach ($header as $column) {
        if (isset($line_values[$count])) {
          $result[$column] = trim($line_values[$count]);
        }
        ++$count;
      }
      // And warn the user about this line.
      \Drupal::messenger()->addWarning($this->t('The line @line does not match the header columns, it has still been imported but might lead to prepopulate issues.', [
        '@line' => implode($this->getDelimiter(), $line_values),
      ]));
    }
    else {
      $count = 0;
      while(count($header) > $count) {
        $result[$header[$count]] = trim($line_values[$count]);
        ++$count;
      }
    }
    return $result;
  }

  /**
   * Persists a file in the database.
   *
   * @param int $fid
   * @param string $webform_id
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function persistWebformDataFromFile($fid, $webform_id) {
    try {
      /** @var \Drupal\file\FileStorageInterface $fileStorage */
      $fileStorage = $this->entityTypeManager->getStorage('file');
    }
    catch (\Throwable $exception) {
      \Drupal::messenger()->addError($exception->getMessage());
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $fileStorage->load($fid);
    $file->setTemporary(); // check if this is useful.

    if (
      $this->validateWebformSchema($webform_id, $file) &&
      $this->saveFileData($webform_id, $file)
    ) {
      \Drupal::messenger()->addMessage($this->t('The file has been saved into the database.'));
    }
    else {
      \Drupal::messenger()->addError($this->t('There was and error while saving the prepopulate file into the database.'));
    }

    // Always delete the file.
    $file->delete();
  }

  /**
   * Returns the data associated to a hash for a Webform.
   *
   * @param string $hash
   * @param string $webform_id
   *
   * @return array
   */
  public function getDataFromHash($hash, $webform_id) {
    $result = [];
    $query = $this->connection->select('webform_prepopulate', 'wp');
    $query->condition('wp.hash', $hash)
          ->condition('wp.webform_id', $webform_id);
    $query->fields('wp', ['data']);
    $data = $query->execute()->fetchField();
    if ($data) {
      $result = unserialize($data);
    }
    return $result;
  }

  /**
   * Returns the data associated to a Webform.
   *
   * @param string $webform_id
   *
   * @return array
   */
  public function listData($webform_id, $header, $search) {
    $query = $this->connection
      ->select('webform_prepopulate', 'wp')
      ->condition('wp.webform_id', $webform_id)
      ->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header)
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(25) // @todo set limit.
      ->fields('wp');

    if ($search) {
      // @todo add LIKE
    }
    $result = $query->execute()->fetchAll();
    return $result;
  }

}
