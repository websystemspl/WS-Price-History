<?php

namespace WsPriceHistory\App;

use DateTime;

class Record
{
  private $id;
  private $postId;
  private $date;
  private $price;

  public function __construct(?string $id, string $postId, string $price, DateTime $date)
  {
    $this->setId($id);
    $this->setPostId($postId);
    $this->setPrice($price);
    $this->setDate($date);
  }

  public function getId()
  {
    return $this->id;
  }

  public function getPostId()
  {
    return $this->postId;
  }

  public function getDate()
  {
    return $this->date;
  }

  public function getPrice()
  {
    return $this->price;
  }

  public function setId($id)
  {
    $this->id = $id;
  }

  public function setPostId($postId)
  {
    $this->postId = $postId;
  }

  public function setDate($date)
  {
    $this->date = $date;
  }

  public function setPrice($price)
  {
    $this->price = $price;
  }
}
