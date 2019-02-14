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
   * Drupal\Core\Database\Driver\sqlite\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\sqlite\Connection
   */
  protected $database;

  /**
   * CSV delimiter.
   *
   * @var string
   */
  private $delimiter;

  /**
   * Constructs a new WebformPrepopulateStorage object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
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
   * Deletes entries from the database.
   *
   * @param $webform_id
   *
   * @return bool
   */
  private function deleteWebformData($webform_id) {
    // @todo implement.
    $result = TRUE;
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
  private function validateWebformSchema($webform_id, File $file) {
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
  private function getWebformKeysFromFile($webform_id, File $file) {
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
  private function getFileHeader(File $file) {
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
   * Save File into the database.
   *
   * @param $webform_id
   * @param \Drupal\file\Entity\File $file
   *
   * @return bool
   */
  private function saveFileData($webform_id, File $file) {
    // @todo implement.
    $result = TRUE;
    return $result;
  }

  /**
   * Persists a file in the database.
   *
   * @param $fid
   * @param $webform_id
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
      $this->deleteWebformData($webform_id) &&
      $this->saveFileData($webform_id, $file)
    ) {
      \Drupal::messenger()->addMessage($this->t('The file has been saved into the database.'));
    }
    else {
      \Drupal::messenger()->addError($this->t('There was and error while saving the file into the database.'));
    }

    // Always delete the file.
    $file->delete();
  }

}
