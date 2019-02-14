<?php

namespace Drupal\webform_prepopulate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Driver\sqlite\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;

/**
 * Class WebformPrepopulateStorage.
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
   * Constructs a new WebformPrepopulateStorage object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
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
   * @todo additionally, checks if the datatype is valid.
   *
   * @param $webform_id
   * @param \Drupal\file\Entity\File $file
   *
   * @return bool
   */
  private function validateWebformSchema($webform_id, File $file) {
    // @todo implement.
    $result = TRUE;
    $header = $this->getFileHeader($file);
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
    // @todo implement.
    $result = [];
    return $result;
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
