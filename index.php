<?php

include('cpu_ops.php');

class PPU
{
    static $VRAM;
    static $oam;
    static $pal;

    static $PPUCTRL;
    static $PPUMASK;
    static $PPUSTATUS;
    static $OAMADDR;
    static $OAMDATA;
    static $PPUSCROLL;
    static $PPUADDR;
    static $PPUDATA;

    static $firstaddr=1;

    static function init()
    {
        self::$VRAM = new SplFixedArray(0x2000);
        self::$oam = new SplFixedArray(0x100);
        self::$pal = new SplFixedArray(0x20);
    }

    static function dump()
    {
        for($i=0;$i<0x100;$i++)
        {
            if($i%16==0)
                printf("\n %02d: ",$i/16);
            else
                printf("%02x, ",self::$oam[$i]);
        }
        print("\n\n");
    }

    static function ppu_write($addr,$value)
    {
        switch($addr)
        {
            case 0x2000:    // status
                self::$PPUCTRL=$value;
                break;
            case 0x2001:    // mask
                self::$PPUMASK=$value;
                break;
            case 0x2003:    // oam address
                self::$OAMADDR=$value;
                break;
            case 0x2004:    // oam data
                self::$oam[self::$OAMADDR++]=$value;
                break;
            case 0x2005:    // scroll
                break;
            case 0x2006:    // address
                if(self::$firstaddr)
                    self::$PPUADDR=$value<<8;
                else
                    self::$PPUADDR|=$value;
                self::$firstaddr^=1;
                var_dump(self::$PPUADDR);
                CPU::$opbreak=TRUE;
                break;
            case 0x2007:    // data
                $addr = self::$PPUADDR&0x3fff;
                if($addr<0x2000)
                    self::$VRAM[$addr&0x3ff]=$value;
                elseif($addr<0x3000)
                    0;
                else 0;
                break;
        }
    }

    static function vblank_set()
    {
        // set VBLANK started status flag
        self::$PPUSTATUS |= 0x80;
    }

    static function vblank_unset()
    {
        // set VBLANK finished status flag
        self::$PPUSTATUS &= 0x7f;
    }

    static function check_interrupt()
    {
        // check if interrupt is enabled on VBLANK
        if(self::$PPUCTRL&0x80)
            return TRUE;
        else
            return FALSE;
    }

    static function oam_dma($value)
    {
        $data = $value*0x100;
        for($i=0;$i<256;$i++)
            self::$oam[$i]=MEM::$RAM[$data++];
    }

    static function ppu_read($addr)
    {
        //print_r(debug_backtrace());

        switch($addr)
        {
            case 0x2002:    //status
                $st = self::$PPUSTATUS;
                self::$PPUSTATUS&=0x7f;
                return $st;
            case 0x2004;    //OAM
                break;
            case 0x2007:    //data
                break;
        }
    }
}

class MEM
{
    static $RAM;   // system ram
    static $PRAM;  // cartridge extra ram
    static $PROM;  // program rom
    static $VROM;  // chr rom

    static $romcnt;
    static $vromcnt;
    static $ctrlbyte;
    static $mappernum;

    static function init()
    {
        self::$RAM = new SplFixedArray(0x800);
        self::$PRAM = new SplFixedArray(0x2000);
        self::$PROM = new SplFixedArray(1);
        self::$VROM = new SplFixedArray(1);
    }

    static function dump()
    {
        for($i=0;$i<0x100;$i++)
        {
            if($i%16==0)
                printf("\n %02d: ",$i/16);
            else
                printf("%02x, ",self::$RAM[$i]);
        }
        print("\n");
    }

    static function write_mem8($addr,$value)
    {
        printf("mem write addr: 0x%04x, 0x%02x\n",$addr,$value);

        if($addr<0x2000) self::$RAM[$addr&0x7ff]=$value;
        elseif($addr<0x4000) PPU::ppu_write($addr, $value);
        elseif($addr<0x4018)
        {
            switch($addr&0x1f)
            {
                case 0x14:  PPU::oam_dma($value); break;
            }
        }
    }

    static function read_mem8($addr)
    {
        if($addr<0x2000) return self::$RAM[$addr&0x7ff];
        elseif($addr<0x4000) return PPU::ppu_read($addr);
        elseif($addr<0x4018)
        {
//            switch($addr&0x1f)
//            {
//                case 0x14: // OAM DMA: Copy 256 bytes from RAM into PPU's sprite memory
//                    if(write) for(unsigned b=0; b<256; ++b) WB(0x2004, RB((v&7)*0x0100+b));
//                    return 0;
//                case 0x15: if(!write) return APU::Read();    APU::Write(0x15,v); break;
//                case 0x16: if(!write) return IO::JoyRead(0); IO::JoyStrobe(v); break;
//                case 0x17: if(!write) return IO::JoyRead(1); // write:passthru
//                default: if(!write) break;
//                         APU::Write(addr&0x1F, v);
//            }
        }
        else
            return self::$PROM[$addr&0x7fff];
    }

    static function write_mem16($addr,$value)
    {
        self::write_mem8($addr, $value&0xff);
        self::write_mem8($addr+1, $value>>8);
    }

    static function read_mem16($addr)
    {
        return self::read_mem8($addr)|self::read_mem8($addr+1)<<8;
    }

    static function load_rom($file)
    {
        $f = fopen($file,'rb');

        if(fgetc($f)=='N' && fgetc($f)=='E' && fgetc($f)=='S' && unpack("C",fgetc($f))[1]==0x1a)
        {
            print("Valid!\n");

            self::$romcnt=unpack("C",fgetc($f))[1];
            self::$vromcnt=unpack("C",fgetc($f))[1];
            self::$ctrlbyte=unpack("C",fgetc($f))[1];
            self::$mappernum=unpack("C",fgetc($f))[1]|(self::$ctrlbyte>>4);

            printf("Mapper: %d\n",self::$mappernum);
            printf("PRG: %d\n",self::$romcnt);
            printf("CHR: %d\n",self::$vromcnt);
            sleep(10);

            fgetc($f);fgetc($f);fgetc($f);fgetc($f);fgetc($f);fgetc($f);fgetc($f);fgetc($f);
            if(self::$mappernum>=0x40) self::$mappernum &= 15;

            $romsize=self::$romcnt*0x4000;
            $vromsize=self::$vromcnt*0x2000;

            // resize PROM buffer
            self::$PROM->setSize($romsize);
            self::$VROM->setSize($vromsize);

            // unpack all bytes into unsigned char format, even if PHP treats them
            // as a signed integer. Yes its ugly, but unfortunately its a language limitation
            for($i=0;$i<$romsize;$i++)
                self::$PROM[$i]=unpack("C",fgetc($f))[1];
            for($i=0;$i<$vromsize;$i++)
                self::$VROM[$i]=unpack("C",fgetc($f))[1];
        }

        fclose($f);
    }
}

class CPU
{
    use CPU_addrmode, CPU_opcodes;

    static $PC;    // program counter
    static $SP;    // stack pointer
    static $ST;    // status registers
    static $A;     // A register
    static $X;     // X register
    static $Y;     // Y register

    static $opbreak;

    static $memaddr;
    static $opcount;
    static $opcode;

    static function boot()
    {
        // reset vector
        self::$PC=MEM::read_mem16(0xfffc);

        self::$A=0;
        self::$X=0;
        self::$Y=0;
        self::$SP=0xff;
        self::$ST=0;

        self::$opcount=0;
        self::$memaddr=0;
    }

    static function print_op()
    {
        $index=array(10,1,34,10,13,13,13,13,13,13,34,4,2,4,13,13,36,1,34,2,2,0,13,13,13,13,34,3,2,3,13,13,
                     9,9,34,11,13,13,13,13,13,13,34,5,2,5,13,13,13,1,34,8,13,13,13,13,13,13,34,7,2,7,13,13,
                     28,3,1,10,13,13,13,13,6,4,1,4,39,4,13,13,38,1,1,2,39,0,13,13,6,3,1,3,39,3,13,13,
                     7,9,1,11,13,13,13,13,13,13,1,5,39,5,13,13,47,1,1,8,13,13,13,13,13,13,1,7,39,7,13,13,
                     41,1,25,10,13,13,13,13,13,13,25,4,33,4,13,13,35,1,25,2,33,0,13,13,27,3,25,3,33,3,13,13,
                     11,9,25,11,13,13,13,13,13,13,25,5,33,5,13,13,15,1,25,8,13,13,13,13,13,13,25,7,33,7,13,13,
                     42,1,0,10,13,13,13,13,13,13,0,4,40,4,13,13,37,1,0,2,40,0,13,13,27,12,0,3,40,3,13,13,
                     12,9,0,11,13,13,13,13,13,13,0,5,40,5,13,13,49,1,0,8,13,13,13,13,13,13,0,7,40,7,13,13,
                     13,13,44,10,13,13,13,13,46,4,44,4,45,4,13,13,22,1,13,13,52,1,13,13,46,3,44,3,45,3,13,13,
                     3,9,44,11,13,13,13,13,46,5,44,5,45,6,13,13,53,1,44,8,55,1,13,13,13,13,44,7,13,13,13,13,
                     32,2,29,10,31,2,13,13,32,4,29,4,31,4,13,13,51,1,29,2,50,1,13,13,32,3,29,3,31,3,13,13,
                     4,9,29,11,13,13,13,13,32,5,29,5,31,6,13,13,16,1,29,8,54,1,13,13,32,7,29,7,31,8,13,13,
                     19,2,17,10,13,13,13,13,19,4,17,4,20,4,13,13,24,1,17,2,21,1,13,13,19,3,17,3,20,3,13,13,
                     8,9,17,11,13,13,13,13,13,13,17,5,20,5,13,13,14,1,17,8,13,13,13,13,13,13,17,7,20,7,13,13,
                     18,2,43,10,13,13,13,13,18,4,43,4,26,4,13,13,23,1,43,2,30,1,13,13,18,3,43,3,26,3,13,13,
                     5,9,43,11,13,13,13,13,13,13,43,5,26,5,13,13,48,1,43,8,13,13,13,13,13,13,43,7,26,7,13,13);

        $mn=array("adc","and","asl","bcc","bcs","beq","bit","bmi",
                 "bne","bpl","brk","bvc","bvs","clc","cld","cli",
                 "clv","cmp","cpx","cpy","dec","dex","dey","inx",
                 "iny","eor","inc","jmp","jsr","lda","nop","ldx",
                 "ldy","lsr","ora","pha","php","pla","plp","rol",
                 "ror","rti","rts","sbc","sta","stx","sty","sec",
                 "sed","sei","tax","tay","txa","tya","tsx","txs");

        return strtoupper($mn[$index[self::$opcode*2]]);
    }

    static function exec_op($cnt)
    {
        while($cnt--)
        {
            // fetch opcode
            self::$opcode = MEM::read_mem8(self::$PC);
            self::$opcount++;

            printf("Current OpCode: 0x%02x (%s)\n",self::$opcode,self::print_op());

            switch(self::$opcode)
            {
                case 0x18: self::m6502_imp();   self::m6502_clc(); break;
                case 0x38: self::m6502_imp();   self::m6502_sec(); break;
                case 0xd8: self::m6502_imp();   self::m6502_cld(); break;
                case 0xf8: self::m6502_imp();   self::m6502_sed(); break;
                case 0x58: self::m6502_imp();   self::m6502_cli(); break;
                case 0x78: self::m6502_imp();   self::m6502_sei(); break;
                case 0xb8: self::m6502_imp();   self::m6502_clv(); break;

                // Transfers / Stack
                case 0xaa: self::m6502_imp();   self::m6502_tax(); break;
                case 0x8a: self::m6502_imp();   self::m6502_txa(); break;
                case 0xa8: self::m6502_imp();   self::m6502_tay(); break;
                case 0x98: self::m6502_imp();   self::m6502_tya(); break;
                case 0xba: self::m6502_imp();   self::m6502_tsx(); break;
                case 0x9a: self::m6502_imp();   self::m6502_txs(); break;
                case 0x68: self::m6502_imp();   self::m6502_pla(); break;
                case 0x48: self::m6502_imp();   self::m6502_pha(); break;
                case 0x28: self::m6502_imp();   self::m6502_plp(); break;
                case 0x08: self::m6502_imp();   self::m6502_php(); break;

                // Stores ---
                case 0x85: self::m6502_zp();    self::m6502_sta(); break;
                case 0x95: self::m6502_zpx();   self::m6502_sta(); break;
                case 0x81: self::m6502_indx();  self::m6502_sta(); break;
                case 0x91: self::m6502_indy();  self::m6502_sta(); break;
                case 0x8d: self::m6502_abs();   self::m6502_sta(); break;
                case 0x9d: self::m6502_absx();  self::m6502_sta(); break;
                case 0x99: self::m6502_absy();  self::m6502_sta(); break;

                case 0x86: self::m6502_zp();    self::m6502_stx(); break;
                case 0x96: self::m6502_zpy();   self::m6502_stx(); break;
                case 0x8e: self::m6502_abs();   self::m6502_stx(); break;

                case 0x84: self::m6502_zp();    self::m6502_sty(); break;
                case 0x94: self::m6502_zpx();   self::m6502_sty(); break;
                case 0x8c: self::m6502_abs();   self::m6502_sty(); break;

                // Loads ---
                case 0xa9: self::m6502_imm();   self::m6502_lda(); break;
                case 0xa5: self::m6502_zp();    self::m6502_lda(); break;
                case 0xb5: self::m6502_zpx();   self::m6502_lda(); break;
                case 0xa1: self::m6502_indx();  self::m6502_lda(); break;
                case 0xb1: self::m6502_indy();  self::m6502_lda(); break;
                case 0xad: self::m6502_abs();   self::m6502_lda(); break;
                case 0xbd: self::m6502_absx();  self::m6502_lda(); break;
                case 0xb9: self::m6502_absy();  self::m6502_lda(); break;

                case 0xa2: self::m6502_imm();   self::m6502_ldx(); break;
                case 0xa6: self::m6502_zp();    self::m6502_ldx(); break;
                case 0xb6: self::m6502_zpy();   self::m6502_ldx(); break;
                case 0xae: self::m6502_abs();   self::m6502_ldx(); break;
                case 0xbe: self::m6502_absy();  self::m6502_ldx(); break;

                case 0xa0: self::m6502_imm();   self::m6502_ldy(); break;
                case 0xa4: self::m6502_zp();    self::m6502_ldy(); break;
                case 0xb4: self::m6502_zpx();   self::m6502_ldy(); break;
                case 0xac: self::m6502_abs();   self::m6502_ldy(); break;
                case 0xbc: self::m6502_absx();  self::m6502_ldy(); break;

                //  Logical ---
                case 0x6a: self::m6502_imp();   self::m6502_ror(); break;
                case 0x66: self::m6502_zp();    self::m6502_ror(); break;
                case 0x76: self::m6502_zpx();   self::m6502_ror(); break;
                case 0x6e: self::m6502_abs();   self::m6502_ror(); break;
                case 0x7e: self::m6502_absx();  self::m6502_ror(); break;

                case 0x4a: self::m6502_imp();   self::m6502_lsr(); break;
                case 0x46: self::m6502_zp();    self::m6502_lsr(); break;
                case 0x56: self::m6502_zpx();   self::m6502_lsr(); break;
                case 0x4e: self::m6502_abs();   self::m6502_lsr(); break;
                case 0x5e: self::m6502_absx();  self::m6502_lsr(); break;

                case 0x2a: self::m6502_imp();   self::m6502_rol(); break;
                case 0x26: self::m6502_zp();    self::m6502_rol(); break;
                case 0x36: self::m6502_zpx();   self::m6502_rol(); break;
                case 0x2e: self::m6502_abs();   self::m6502_rol(); break;
                case 0x3e: self::m6502_absx();  self::m6502_rol(); break;

                case 0x0a: self::m6502_imp();   self::m6502_asl(); break;
                case 0x06: self::m6502_zp();    self::m6502_asl(); break;
                case 0x16: self::m6502_zpx();   self::m6502_asl(); break;
                case 0x0e: self::m6502_abs();   self::m6502_asl(); break;
                case 0x1e: self::m6502_absx();  self::m6502_asl(); break;

                case 0xca: self::m6502_imp();   self::m6502_dex(); break;
                case 0x88: self::m6502_imp();   self::m6502_dey(); break;
                case 0xe8: self::m6502_imp();   self::m6502_inx(); break;
                case 0xc8: self::m6502_imp();   self::m6502_iny(); break;

                case 0xc6: self::m6502_zp();    self::m6502_dec(); break;
                case 0xd6: self::m6502_zpx();   self::m6502_dec(); break;
                case 0xce: self::m6502_abs();   self::m6502_dec(); break;
                case 0xde: self::m6502_absx();  self::m6502_dec(); break;

                case 0xe6: self::m6502_zp();    self::m6502_inc(); break;
                case 0xf6: self::m6502_zpx();   self::m6502_inc(); break;
                case 0xee: self::m6502_abs();   self::m6502_inc(); break;
                case 0xfe: self::m6502_absx();  self::m6502_inc(); break;

                case 0xe0: self::m6502_imm();   self::m6502_cpx(); break;
                case 0xe4: self::m6502_zp();    self::m6502_cpx(); break;
                case 0xec: self::m6502_abs();   self::m6502_cpx(); break;

                case 0xc0: self::m6502_imm();   self::m6502_cpy(); break;
                case 0xc4: self::m6502_zp();    self::m6502_cpy(); break;
                case 0xcc: self::m6502_abs();   self::m6502_cpy(); break;

                case 0xc9: self::m6502_imm();   self::m6502_cmp(); break;
                case 0xc5: self::m6502_zp();    self::m6502_cmp(); break;
                case 0xd5: self::m6502_zpx();   self::m6502_cmp(); break;
                case 0xc1: self::m6502_indx();  self::m6502_cmp(); break;
                case 0xd1: self::m6502_indy();  self::m6502_cmp(); break;
                case 0xcd: self::m6502_abs();   self::m6502_cmp(); break;
                case 0xdd: self::m6502_absx();  self::m6502_cmp(); break;
                case 0xd9: self::m6502_absy();  self::m6502_cmp(); break;

                case 0xe9: self::m6502_imm();   self::m6502_sbc(); break;
                case 0xe5: self::m6502_zp();    self::m6502_sbc(); break;
                case 0xf5: self::m6502_zpx();   self::m6502_sbc(); break;
                case 0xe1: self::m6502_indx();  self::m6502_sbc(); break;
                case 0xf1: self::m6502_indy();  self::m6502_sbc(); break;
                case 0xed: self::m6502_abs();   self::m6502_sbc(); break;
                case 0xfd: self::m6502_absx();  self::m6502_sbc(); break;
                case 0xf9: self::m6502_absy();  self::m6502_sbc(); break;

                case 0x69: self::m6502_imm();   self::m6502_adc(); break;
                case 0x65: self::m6502_zp();    self::m6502_adc(); break;
                case 0x75: self::m6502_zpx();   self::m6502_adc(); break;
                case 0x61: self::m6502_indx();  self::m6502_adc(); break;
                case 0x71: self::m6502_indy();  self::m6502_adc(); break;
                case 0x6d: self::m6502_abs();   self::m6502_adc(); break;
                case 0x7d: self::m6502_absx();  self::m6502_adc(); break;
                case 0x79: self::m6502_absy();  self::m6502_adc(); break;

                case 0x09: self::m6502_imm();   self::m6502_ora(); break;
                case 0x05: self::m6502_zp();    self::m6502_ora(); break;
                case 0x15: self::m6502_zpx();   self::m6502_ora(); break;
                case 0x01: self::m6502_indx();  self::m6502_ora(); break;
                case 0x11: self::m6502_indy();  self::m6502_ora(); break;
                case 0x0d: self::m6502_abs();   self::m6502_ora(); break;
                case 0x1d: self::m6502_absx();  self::m6502_ora(); break;
                case 0x19: self::m6502_absy();  self::m6502_ora(); break;

                case 0x29: self::m6502_imm();   self::m6502_and(); break;
                case 0x25: self::m6502_zp();    self::m6502_and(); break;
                case 0x35: self::m6502_zpx();   self::m6502_and(); break;
                case 0x21: self::m6502_indx();  self::m6502_and(); break;
                case 0x31: self::m6502_indy();  self::m6502_and(); break;
                case 0x2d: self::m6502_abs();   self::m6502_and(); break;
                case 0x3d: self::m6502_absx();  self::m6502_and(); break;
                case 0x39: self::m6502_absy();  self::m6502_and(); break;

                case 0x49: self::m6502_imm();   self::m6502_eor(); break;
                case 0x45: self::m6502_zp();    self::m6502_eor(); break;
                case 0x55: self::m6502_zpx();   self::m6502_eor(); break;
                case 0x41: self::m6502_indx();  self::m6502_eor(); break;
                case 0x51: self::m6502_indy();  self::m6502_eor(); break;
                case 0x4d: self::m6502_abs();   self::m6502_eor(); break;
                case 0x5d: self::m6502_absx();  self::m6502_eor(); break;
                case 0x59: self::m6502_absy();  self::m6502_eor(); break;

                case 0x24: self::m6502_zp();    self::m6502_bit(); break;
                case 0x2c: self::m6502_abs();   self::m6502_bit(); break;

                // Branches ---
                case 0x10: self::m6502_rel();   self::m6502_bpl(); break;
                case 0x30: self::m6502_rel();   self::m6502_bmi(); break;
                case 0x50: self::m6502_rel();   self::m6502_bvc(); break;
                case 0x70: self::m6502_rel();   self::m6502_bvs(); break;
                case 0x90: self::m6502_rel();   self::m6502_bcc(); break;
                case 0xb0: self::m6502_rel();   self::m6502_bcs(); break;
                case 0xd0: self::m6502_rel();   self::m6502_bne(); break;
                case 0xf0: self::m6502_rel();   self::m6502_beq(); break;

                // Jumps / Calls ---
                case 0x20: self::m6502_abs();   self::m6502_jsr(); break;
                case 0x60: self::m6502_imp();   self::m6502_rts(); break;
                case 0x4c: self::m6502_abs();   self::m6502_jmp(); break;
                case 0x6c: self::m6502_ind();   self::m6502_jmp(); break;

                // System calls ---
                case 0x00: self::m6502_imp();   self::m6502_brk(); break;
                case 0xea: self::m6502_imp();   self::m6502_nop(); break;
                case 0x40: self::m6502_imp();   self::m6502_rti(); break;

            default:
                printf("Unimplemented OPCode: Hex: 0x%02x, Dec: %d\n",self::$opcode,self::$opcode);
                exit(0);
                break;

            }

            self::$opcount++;

            if(self::$opcount%10000==0)
                printf("Executed %lu opcodes\n",self::$opcount);

            printf("Registers: A: 0x%02x, X: 0x%02x, Y: 0x%02x, SP: 0x%02x, PC: 0x%04x, ST: ",self::$A,self::$X,self::$Y,self::$SP,self::$PC);
            if(self::$ST & REG_N) print("N"); else print("-");
            if(self::$ST & REG_V) print("V"); else print("-");
            if(self::$ST & REG_B) print("B"); else print("-");
            if(self::$ST & REG_D) print("D"); else print("-");
            if(self::$ST & REG_I) print("I"); else print("-");
            if(self::$ST & REG_Z) print("Z"); else print("-");
            if(self::$ST & REG_C) print("C"); else print("-");
            print("\n\n");

            if(self::$opbreak)
                fread(STDIN,1);

            //if(self::$opcount>100000)
            //    fread(STDIN,1);

        }
    }
}

MEM::init();

MEM::load_rom('/home/cassiano/mario.nes');
CPU::boot();

while(1)
{
    CPU::exec_op(4972);
    CPU::exec_op(4972);
    CPU::exec_op(4972);
    CPU::exec_op(4972);
    CPU::exec_op(4972);

    PPU::vblank_set();

    if(PPU::check_interrupt())
        CPU::execute_irq();

    CPU::exec_op(4972);

    PPU::vblank_unset();
}

//    printf("0x%02x\n",$rom[$bootaddr&0x7fff]);
//    $bootaddr++;
 //   printf("0x%02x\n",$rom[$bootaddr&0x7fff]<<8);
 
//    printf("0x%04x\n",$rom[$bootaddr&0x7fff]|$rom[++$bootaddr&0x7fff]<<8);