/*!
 * File uploader
 *
 * wpdeps=jquery,vue-js
 */

/*global TaroimgUploader: false*/

(function ($) {

  'use strict';


  Vue.component('taroimg-uploader', {
    template: `
    <div>

    <form :action="actionUrl" enctype="multipart/form-data" method="post"
			:class="{'taroimg-form': true, undroppable: undroppable, uploading: uploading}"
			:id="formId"
			@submit.prevent="submitForm" ref="form">
			<input type="hidden" name="_wpnonce" :value="nonce" />
			<div :class="{'taroimg-dropzone': true, on: onDrag}"
			    @dragover.prevent.stop="dragOver"
			    @dragenter.prevent.stop="dragEnter"
			    @dragLeave.prevent.stop="dragLeave"
			    @drop.prevent="dropHandler"
			    >
				<p class="taroimg-description">
					<span v-if="!undroppable" class="taroimg-drop-here">
					    {{dropMsg}}
					    <br />
                    </span>
					<button class="taroimg-button" type="button" @click="openFile">{{btnLabel}}</button>
				</p>
				
				<div class="taroimg-progress-bar">
					<div class="taroimg-progress-inner" :style="{width: percentile}"></div>
					<div class="taroimg-progress-text">{{percentile}}</div>
				</div>
			</div>
			<input class="taroimg-input" type="file" name="file" @change="fileChangeHandler" ref="file">
    </form>
    </div>
    `,
    data: function(){
      return {
        dropMsg: TaroimgUploader.dropMsg,
        uploading: false,
        undroppable: !window.FileReader,
        btnLabel: TaroimgUploader.btnLabel,
        nonce: TaroimgUploader.nonce,
        loaded: 0,
        total: 0,
        onDrag: false
      };
    },
    props: {
      directory: {
        type: String,
        required: true
      }
    },
    computed: {
      actionUrl: function(){
        return TaroimgUploader.endpoint + this.directory;
      },
      formId: function(){
        return `taroimg-${this.directory}`;
      },
      percentile: function(){
        if(!this.total){
          return '';
        }else{
          return Math.round( this.loaded / this.total * 100 ) + '%';
        }
      },
    },
    methods: {
      upload: function($form, formData){
        // Avoid multiple uploading.
        if(this.uploading){
          return;
        }
        this.uploading = true;
        let self = this;
        $.ajax({
          url: $form.attr('action'),
          method: 'post',
          dataType: 'json',
          data: formData,
          processData: false,
          contentType: false,
          xhr : function(){
            let XHR = $.ajaxSettings.xhr();
            if(XHR.upload){
              XHR.upload.addEventListener('progress',function(e){
                self.loaded = e.loaded;
                self.total  = e.total;
              }, false);
            }
            return XHR;
          }
        }).done(function( res ) {
          $form[0].reset();
          self.$emit('upload-finished', res);
        }).fail(function( response ) {
          self.$emit('upload-error', response);
        }).always(function(){
          self.uploading = false;
          self.loaded = 0;
          self.total  = 0;
        });
      },

      submitForm: function(){
        // Send file via Ajax
        let $form = $(this.$refs.form);
        let formData = new FormData( this.$refs.form );
        this.upload($form, formData);
      },

      /**
       * Trigger file select.
       */
      openFile: function(){
        this.$refs.file.dispatchEvent(new Event('click'));
      },

      /**
       * Trigger submit if file selected.
       *
       * @param {Event} event
       */
      fileChangeHandler: function(event){
        if(event.target.files.length){
          this.submitForm();
        }
      },

      dragOver: function(event){
        this.onDrag = true;
        return false;
      },

      dragEnter: function(){
        return false;
      },

      dragLeave: function(){
        this.onDrag = false;
        return false;
      },
      dropHandler: function(event){
        // Get file.
        if(!event.dataTransfer.files.length){
          return false;
        }
        event.stopPropagation();
        // Only 1 file.
        let file = event.dataTransfer.files[0];
        let form = this.$refs.form;
        let formData = new FormData( form );
        formData.append('file', file);
        this.upload($(form), formData);
        return false;
      }
    }
  });
})(jQuery);
