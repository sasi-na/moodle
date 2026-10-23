<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package   theme_baz
 * @copyright Copyright © 2021 onwards Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */


defined('MOODLE_INTERNAL') || die();

$page = new admin_settingpage('theme_baz_block16', get_string('settingsblock16', 'theme_baz'));

          $name = 'theme_baz/displayblock16';
          $title = get_string('turnon', 'theme_baz');
          $description = get_string('displayblock16_desc', 'theme_baz');
          $default = 0;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/displayhrblock16';
          $title = get_string('displayblockhr', 'theme_baz');
          $description = get_string('displayblockhr_desc', 'theme_baz');
          $default = 1;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block16class';
          $title = get_string('additionalclass', 'theme_baz');
          $description = get_string('additionalclass_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtext($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block16introtitle';
          $title = get_string('blockintrotitle', 'theme_baz');
          $description = get_string('blockintrotitle_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block16introcontent';
          $title = get_string('blockintrocontent', 'theme_baz');
          $description = get_string('blockintrocontent_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block16htmlcontent';
          $title = get_string('blockhtmlcontent', 'theme_baz');
          $description = get_string('blockhtmlcontent_desc', 'theme_baz');
          $default = '<div class="swiper swiper-block--16 rui--swiper-arrows">
          <div class="swiper-wrapper">
              <!-- Start item -->
              <div class="swiper-slide rui-card-item">
                  <div class="rui-card h-100" style="
          max-width: 100%;
      ">
                      <div class="rui-card-body">
                          <h4 class="mb-3">Indigenous peoples Education Australia Queensland.</h4>
                          <a href="https://rosea.io">
                              <div class="rui-img--rounded-fluid"><img src="https://demo.rosea.io/baz/1/pluginfile.php/1/theme_baz/ruisettingsimgs/0/img-4.jpg?time=1635624685924" alt="img 4" width="400" height="225"></div>
                          </a>
                          <p class="rui-card-text mt-3">Auto-generated reports: Get accurate insights on your performance with auto-generated reports that help create successful campaigns.</p>
                          <ul class="rui-special-list rui-special-list--light">
                              <li>Personal asset watchlists</li>
                              <li>Curated market data feed</li>
                              <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
                          </ul>
                          <a href="https://rosea.io" class="rui-card-btn-link mt-2">Learn more about the Smart Keyboard</a>
                      </div>
                  </div>
              </div>
              <!-- End item -->
      
              <!-- Start item -->
              <div class="swiper-slide rui-card-item">
                  <div class="rui-card h-100">
                      <div class="rui-card-body">
                          <span class="badge-xs badge-warning">Updated: October 11, 2011</span>
                          <h4 class="mt-2 mb-3">Aboriginal Australians Study and teaching Australia Queensland.</h4>
                          <a href="https://rosea.io">
                              <div class="rui-img--rounded-fluid"><img src="https://demo.rosea.io/baz/1/pluginfile.php/1/theme_baz/ruisettingsimgs/0/img-5.jpg" alt="img 5" width="400" height="225"></div>
                          </a>
                          <p class="rui-card-text mt-3">Auto-generated reports: Get accurate insights on your performance with auto-generated reports that help create successful campaigns.</p>
                          <ul class="rui-special-list rui-special-list--primary">
                              <li>Personal asset watchlists</li>
                              <li>Curated market data feed</li>
                              <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
                          </ul>
                          <a href="https://rosea.io" class="rui-card-btn-link mt-2">Learn more about the Smart Keyboard</a>
                      </div>
                  </div>
              </div>
              <!-- End item -->
      
              <!-- Start item -->
              <div class="swiper-slide rui-card-item">
                  <div class="rui-card h-100">
                      <div class="rui-card-body">
                          <span class="badge-xs badge-info">New</span>
                          <h4 class="mt-2 mb-3">Curriculum-based assessment Aids and devices.</h4>
                          <a href="https://rosea.io">
                              <div class="rui-img--rounded-fluid"><img src="https://demo.rosea.io/baz/1/pluginfile.php/1/theme_baz/ruisettingsimgs/0/img-6.jpg" alt="img 6" width="400" height="225"></div>
                          </a>
                          <p class="rui-card-text mt-3">Auto-generated reports: Get accurate insights on your performance with auto-generated reports that help create successful campaigns.</p>
                          <ul class="rui-special-list rui-special-list--light">
                              <li>Personal asset watchlists</li>
                              <li>Curated market data feed</li>
                              <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
                          </ul>
                          <a href="https://rosea.io" class="rui-card-btn-link mt-2">Learn more about the Smart Keyboard</a>
                      </div>
                  </div>
              </div>
              <!-- End item -->
      
              <!-- Start item -->
              <div class="swiper-slide rui-card-item">
                  <div class="rui-card h-100">
                      <div class="rui-card-body">
                          <h4 class="mb-3">Indigenous peoples Education Australia Queensland.</h4>
                          <a href="https://rosea.io">
                              <div class="rui-img--rounded-fluid"><img src="https://demo.rosea.io/baz/1/pluginfile.php/1/theme_baz/ruisettingsimgs/0/img-4.jpg?time=1635624685924" alt="img 4" width="400" height="225"></div>
                          </a>
                          <p class="rui-card-text mt-3">Auto-generated reports: Get accurate insights on your performance with auto-generated reports that help create successful campaigns.</p>
                          <ul class="rui-special-list rui-special-list--light">
                              <li>Personal asset watchlists</li>
                              <li>Curated market data feed</li>
                              <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
                          </ul>
                          <a href="https://rosea.io" class="rui-card-btn-link mt-2">Learn more about the Smart Keyboard</a>
                      </div>
                  </div>
              </div>
              <!-- End item -->
      
              <!-- Start item -->
              <div class="swiper-slide rui-card-item">
                  <div class="rui-card h-100">
                      <div class="rui-card-body">
                          <h4 class="mb-3">Indigenous peoples Education Australia Queensland.</h4>
                          <a href="https://rosea.io">
                              <div class="rui-img--rounded-fluid"><img src="https://demo.rosea.io/baz/1/pluginfile.php/1/theme_baz/ruisettingsimgs/0/img-4.jpg?time=1635624685924" alt="img 4" width="400" height="225"></div>
                          </a>
                          <p class="rui-card-text mt-3">Auto-generated reports: Get accurate insights on your performance with auto-generated reports that help create successful campaigns.</p>
                          <ul class="rui-special-list rui-special-list--light">
                              <li>Personal asset watchlists</li>
                              <li>Curated market data feed</li>
                              <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
                          </ul>
                          <a href="https://rosea.io" class="rui-card-btn-link mt-2">Learn more about the Smart Keyboard</a>
                      </div>
                  </div>
              </div>
              <!-- End item -->
      
              <!-- Start item -->
              <div class="swiper-slide rui-card-item">
                  <div class="rui-card h-100">
                      <div class="rui-card-body">
                          <h4 class="mb-3">Indigenous peoples Education Australia Queensland.</h4>
                          <a href="https://rosea.io">
                              <div class="rui-img--rounded-fluid"><img src="https://demo.rosea.io/baz/1/pluginfile.php/1/theme_baz/ruisettingsimgs/0/img-4.jpg?time=1635624685924" alt="img 4" width="400" height="225"></div>
                          </a>
                          <p class="rui-card-text mt-3">Auto-generated reports: Get accurate insights on your performance with auto-generated reports that help create successful campaigns.</p>
                          <ul class="rui-special-list rui-special-list--light">
                              <li>Personal asset watchlists</li>
                              <li>Curated market data feed</li>
                              <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
                          </ul>
                          <a href="https://rosea.io" class="rui-card-btn-link mt-2">Learn more about the Smart Keyboard</a>
                      </div>
                  </div>
              </div>
              <!-- End item -->
      
              <!-- Start item -->
              <div class="swiper-slide rui-card-item">
                  <div class="rui-card h-100">
                      <div class="rui-card-body">
                          <h4 class="mb-3">Indigenous peoples Education Australia Queensland.</h4>
                          <a href="https://rosea.io">
                              <div class="rui-img--rounded-fluid"><img src="https://demo.rosea.io/baz/1/pluginfile.php/1/theme_baz/ruisettingsimgs/0/img-4.jpg?time=1635624685924" alt="img 4" width="400" height="225"></div>
                          </a>
                          <p class="rui-card-text mt-3">Auto-generated reports: Get accurate insights on your performance with auto-generated reports that help create successful campaigns.</p>
                          <ul class="rui-special-list rui-special-list--light">
                              <li>Personal asset watchlists</li>
                              <li>Curated market data feed</li>
                              <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
                          </ul>
                          <a href="https://rosea.io" class="rui-card-btn-link mt-2">Learn more about the Smart Keyboard</a>
                      </div>
                  </div>
              </div>
              <!-- End item -->
      
          </div> <!-- swiper-wrapper -->
      
          <div class="d-none d-md-flex swiper-button-next"></div>
          <div class="d-none d-md-flex swiper-button-prev"></div>
          <div class="swiper-pagination"></div>
      </div>';
          $setting = new baz_setting_confightmleditor($name, $title, $description, $default);
          $page->add($setting);


          $name = 'theme_baz/block16footercontent';
          $title = get_string('blockfootercontent', 'theme_baz');
          $description = get_string('blockfootercontent_desc', 'theme_baz');
          $default = '<script>
          var swiper16 = new Swiper(".swiper-block--16", {
            slidesPerView: 3,
            spaceBetween: 30,
            centeredSlides: true,
            navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
                keyboard: {
                    enabled: true,
            },
            pagination: {
              el: ".swiper-pagination",
              clickable: true,
            },
            // Responsive breakpoints
                breakpoints: {
                    // when window width is >= 320px
                    320: {
                        slidesPerView: 1,
                    },
                    // when window width is >= 768px
                    768: {
                        slidesPerView: 2,
                    },
                    // when window width is >= 1000px
                    1100: {
                        slidesPerView: 3,
                    }
                },
          });
        </script>';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

$settings->add($page);