/*!
 * Image container
 *
 * wpdeps=taroimg-uploader
 */

/*global TaroimgContainer: false*/

(function ($) {

  'use strict';

  Vue.component( 'taroimg-item', {
    template: `
      <div :class="{'taroimg-item': true, 'deleting': deleting}" :style="containerStyle">
          <button class="taroimg-item-delete" type="button" @click="deleteImage">&times;</button>
          <img class="taroimg-item-thumbnail" :src="image.thumbnail" :alt="image.title" v-if="image.thumbnail" />
          <span class="taroimg-item-label">{{image.name}}</span>
      </div>
    `,
    data: function(){
      return {
        deleting: false,
      }
    },
    computed: {
      containerStyle: function() {
        return {
          width: TaroimgContainer.width + 'px',
        };
      }
    },
    props: {
      image: {
        type: Object,
        default: null
      },
      endpoint: {
        type: String,
        required: true
      }
    },
    methods: {
      deleteImage: function(){
        let self = this;
        this.deleting = true;
        $.ajax({
          method: 'delete',
          url: this.endpoint + '?attachment=' + self.image.id,
          beforeSend: function ( xhr ) {
            xhr.setRequestHeader( 'X-WP-Nonce', TaroimgContainer.nonce );
          },
        }).done(function(response, status, xhr){
          self.$emit('delete-image', true, self.image.id, '');
        }).fail(function(response){
          let message = '';
          if(response.responseJSON && response.responseJSON.message){
            message = response.responseJSON.message;
          }
          self.$emit('delete-image', false, self.image.id, message);
        }).always(function(){
          self.deleting = false;
        });
      },
    }
  } );

  Vue.component( 'taroimg-container', {
    template: `
    <div>
        <div class="taroimg-list">
            <taroimg-item v-for="image in images" :key="image.id" :endpoint="endpoint" :image="image" @delete-image="deleteImage">
            </taroimg-item>
        </div>
        <taroimg-uploader v-if="uploadable" :directory="directory" @upload-finished="uploadHandler" @upload-error="uploadErrorHandler"></taroimg-uploader>
    </div>
    `,
    data: function(){
      return {
        directory: 'co2',
        images: [],
        limit: 0,
        loading: false,
      }
    },
    computed: {
      uploadable: function(){
        return 0 === this.limit || this.images.length < this.limit;
      },
      endpoint: function(){
        return TaroimgContainer.endpoint + this.directory;
      }
    },
    mounted: function(){
      let self = this;
      this.getImageList(function(response, status, xhr){
      });
    },
    methods: {

      throwError: function(message){
        this.$emit('on-error', message);
      },

      uploadHandler: function(image){
        this.images.push(image);
      },

      uploadErrorHandler: function(event, response){
        let message = '';
        if(response.responseJSON && response.responseJSON.message){
          message = response.responseJSON.message;
        }
        self.throwError(message);
      },

      getImageList: function(callback) {
        let self = this;
        this.loading = true;
        $.ajax({
          method: 'get',
          url: this.endpoint,
          beforeSend: function ( xhr ) {
            xhr.setRequestHeader( 'X-WP-Nonce', TaroimgContainer.nonce );
          },
        }).done(function(response, status, xhr){
          self.images = response.media;
          self.limit  = response.limit;
          if(callback){
            callback(response, status, xhr);
          }
        }).fail(function(response){
          let message = '';
          if(response.responseJSON && response.responseJSON.message){
            message = response.responseJSON.message;
          }
          self.throwError( message );
        }).always(function(){
          self.loading = false;
        });
      },

      deleteImage: function(success, id, message) {
        if(success){
          let index = null;
          let imageToDeleted = null;
          this.images.forEach(function(image, i){
            if(id === image.id){
              index = i;
              imageToDeleted = image;
              return false;
            }
          });
          if(null === index){
            this.throwError(TaroimgContainer.noImageToDelete);
          }else{
            this.images.splice(index, 1);
            this.$emit('image-deleted', imageToDeleted);
          }
        }else{
          this.throwError(message);
        }
      }
    }
  } );



})(jQuery);
