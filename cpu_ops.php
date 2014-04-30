<?php

//     Bit No.       7   6   5   4   3   2   1   0
//                   S   V       B   D   I   Z   C

define('REG_N',(1<<7));
define('REG_V',(1<<6));
define('REG_B',(1<<4));
define('REG_D',(1<<3));
define('REG_I',(1<<2));
define('REG_Z',(1<<1));
define('REG_C',1);

trait CPU_addrmode
{
    static function m6502_acc()
    {
        self::$memaddr = self::$A;
        self::$PC++;
    }

    // implied, only increment PC
    static function m6502_imp()
    {
        self::$PC++;
    }

    // immediate, return next byte addr
    static function m6502_imm()
    {
        self::$memaddr = self::$PC+1;
        self::$PC+=2;
    }

    // Zero Page
    static function m6502_zp()
    {
        self::$memaddr = MEM::read_mem8(self::$PC+1);
        self::$PC+=2;
    }

    // Zero Page, X
    static function m6502_zpx()
    {
        self::$memaddr = self::$X + MEM::read_mem8(self::$PC+1);
        self::$PC+=2;
    }

    // Zero Page, Y
    static function m6502_zpy()
    {
        self::$memaddr = self::$Y + MEM::read_mem8(self::$PC+1);
        self::$PC+=2;
    }

    // relative, return next byte addr
    static function m6502_rel()
    {
        self::$memaddr = self::$PC+1;
        self::$PC+=2;
    }

    // absolute, return 16 bit word
    static function m6502_abs()
    {
        self::$memaddr=MEM::read_mem16(self::$PC+1);
        self::$PC+=3;
    }

    // absolute, X return 16 bit word
    static function m6502_absx()
    {
        self::$memaddr = MEM::read_mem16(self::$PC+1)+self::$X;
        self::$PC+=3;
    }

    // absolute, Y return 16 bit word
    static function m6502_absy()
    {
        self::$memaddr = MEM::read_mem16(self::$PC+1)+self::$Y;
        self::$PC+=3;
    }

    // Indirect
    static function m6502_ind()
    {
        $addr = MEM::read_mem16(self::$PC+1);
        self::$memaddr = MEM::read_mem16($addr);
        self::$PC+=2;
    }

    // Indirect Indexed
    static function m6502_indy()
    {
        $byte = MEM::read_mem8(self::$PC+1);
        self::$memaddr = MEM::read_mem16($byte)+self::$Y;
        self::$PC+=2;
    }

    // Indexed Indirect
    static function m6502_indx()
    {
        $byte = MEM::read_mem8(self::$PC+1)+self::$X;
        self::$memaddr = MEM::read_mem16($byte);
        self::$PC+=2;
    }
}

trait CPU_opcodes
{
    //
    // --- status flags
    static function m6502_sei()
    {
        self::$ST |= REG_I;
    }

    static function m6502_cld()
    {
        self::$ST &= ~REG_D;
    }

    static function m6502_clc()
    {
        self::$ST &= ~REG_C;
    }

    static function m6502_clv()
    {
        self::$ST &= ~REG_V;
    }

    static function m6502_cli()
    {
        self::$ST &= ~REG_I;
    }

    static function m6502_sec()
    {
        self::$ST |= REG_C;
    }

    static function m6502_sed()
    {
        self::$ST |= REG_D;
    }

    //
    // --- stores/loads
    static function m6502_lda()
    {
        self::$A = MEM::read_mem8(self::$memaddr);
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$A==0?REG_Z:0)|(self::$A>>7?REG_N:0);
    }

    static function m6502_ldx()
    {
        self::$X = MEM::read_mem8(self::$memaddr);
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$X==0?REG_Z:0)|(self::$X>>7?REG_N:0);
    }

    static function m6502_ldy()
    {
        self::$Y = MEM::read_mem8(self::$memaddr);
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$Y==0?REG_Z:0)|(self::$Y>>7?REG_N:0);
    }

    static function m6502_sta()
    {
        MEM::write_mem8(self::$memaddr, self::$A);
    }

    static function m6502_stx()
    {
        MEM::write_mem8(self::$memaddr, self::$X);
    }

    static function m6502_sty()
    {
        MEM::write_mem8(self::$memaddr, self::$Y);
    }

    //
    // --- stack
    static function m6502_txs()
    {
        self::$SP=self::$X;
    }

    static function m6502_tsx()
    {
        self::$X = self::$SP;
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$X==0?REG_Z:0)|(self::$X>>7?REG_N:0);
    }

    static function m6502_pha()
    {
        self::$SP--;
        MEM::write_mem8(0x100+self::$SP, self::$A);
    }

    static function m6502_pla()
    {
        self::$A = MEM::read_mem8(0x100+self::$SP);
        self::$SP++;
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$A==0?REG_Z:0)|(self::$A>>7?REG_N:0);
    }

    static function m6502_php()
    {
        self::$SP--;
        MEM::write_mem8(0x100+self::$SP, self::$ST);
    }

    static function m6502_plp()
    {
        self::$ST = MEM::read_mem8(0x100+self::$SP);
        self::$SP++;
    }

    //
    // --- increments/decrements
    static function m6502_inx()
    {
        self::$X++;
        self::$X&=0xff; // PHP: round to unsigned char
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$X==0?REG_Z:0)|(self::$X>>7?REG_N:0);
    }

    static function m6502_iny()
    {
        self::$Y++;
        self::$Y&=0xff; // PHP: round to unsigned char
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$Y==0?REG_Z:0)|(self::$Y>>7?REG_N:0);
    }

    static function m6502_dex()
    {
        self::$X--;
        self::$X&=0xff; // PHP: round to unsigned char
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$X==0?REG_Z:0)|(self::$X>>7?REG_N:0);
    }

    static function m6502_dey()
    {
        self::$Y--;
        self::$Y&=0xff; // PHP: round to unsigned char
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$Y==0?REG_Z:0)|(self::$Y>>7?REG_N:0);
    }

    static function m6502_inc()
    {
        $data = (MEM::read_mem8(self::$memaddr)+1)&0xff;    // round
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= ($data==0?REG_Z:0)|($data>>7?REG_N:0);
        MEM::write_mem8(self::$memaddr, $data);
    }

    static function m6502_dec()
    {
        $data = (MEM::read_mem8(self::$memaddr)-1)&0xff;    // round
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= ($data==0?REG_Z:0)|($data>>7?REG_N:0);
        MEM::write_mem8(self::$memaddr, $data);
    }

    //
    // --- branches
    static function m6502_bne()
    {
        if(!(self::$ST & REG_Z))
            self::branch_jump();
    }

    static function m6502_beq()
    {
        if(self::$ST & REG_Z)
            self::branch_jump();
    }

    static function m6502_bcc()
    {
        if(!(self::$ST & REG_C))
            self::branch_jump();
    }

    static function m6502_bcs()
    {
        if(self::$ST & REG_C)
            self::branch_jump();
    }

    static function m6502_bvc()
    {
        if(!(self::$ST & REG_V))
            self::branch_jump();
    }

    static function m6502_bvs()
    {
        if(self::$ST & REG_V)
            self::branch_jump();
    }

    static function m6502_bpl()
    {
        if(!(self::$ST & REG_N))
            self::branch_jump();
    }

    static function m6502_bmi()
    {
        if(self::$ST & REG_Z)
            self::branch_jump();
    }

    //
    // --- jumps
    static function m6502_jsr()
    {
        self::$SP-=2;
        self::$PC--;

        printf("Sub-Routine jump: 0x%04x\n",self::$memaddr);
        
        MEM::write_mem16(0x100+self::$SP, self::$PC);
        self::$PC = self::$memaddr;
    }

    static function m6502_jmp()
    {
        //self::$opbreak=TRUE;
        self::$PC = self::$memaddr;
    }

    static function m6502_rts()
    {
        self::$PC = MEM::read_mem16(0x100+self::$SP)+1;
        self::$SP+=2;

        printf("Return Sub-Routine jump: 0x%04x\n",self::$PC);
    }

    static function m6502_rti()
    {
        self::$PC = MEM::read_mem16(0x100+self::$SP);
        self::$SP+=2;
    }

    //
    // --- Shifts
    static function m6502_lsr()
    {
        self::$ST &= ~(REG_Z|REG_N|REG_C);

        // accumulator operation
        if((self::$opcode&0x0f)==0xa)
        {
            self::$ST |= (self::$A&1)?REG_C:0;
            self::$A>>=1;
            self::$ST |= (self::$A==0?REG_Z:0)|
                         (self::$A>>7?REG_N:0);
        }
        else
        {
            $data = MEM::read_mem8(self::$memaddr);
            self::$ST |= ($data&1)?REG_C:0;
            $data>>=1;
            self::$ST |= ($data==0?REG_Z:0)|
                         ($data>>7?REG_N:0);

            MEM::write_mem8(self::$memaddr, $data);
        }
        
//        // accumulator shift
//        if((cpu.opcode&0xf)==0xa)
//        {
//            cpu.ps.b.c=cpu.a&1;
//
//            cpu.a>>=1;
//
//            cpu.ps.b.z=(!cpu.a);
//            cpu.ps.b.n=(cpu.a>>7)&1;
//        }
//        else
//        {
//            byte data = read_byte(cpu.memaddr);
//
//            cpu.ps.b.c=data&1;
//
//            data>>=1;
//            cpu.ps.b.z=(!data);
//            cpu.ps.b.n=(data>>7)&1;
//            write_byte(cpu.memaddr,data);
//        }
    }

    static function m6502_asl()
    {
        self::$ST &= ~(REG_Z|REG_N|REG_C);

        // accumulator operation
        if((self::$opcode&0x0f)==0xa)
        {
            self::$ST |= (self::$A&0x80)?REG_C:0;
            self::$A<<=1;
            self::$A&=0xff; // round up to unsigned char
            self::$ST |= (self::$A==0?REG_Z:0)|
                         (self::$A>>7?REG_N:0);
        }
        else
        {
            $data = MEM::read_mem8(self::$memaddr);
            self::$ST |= ($data&1)?REG_C:0;
            $data<<=1;
            $data&=0xff; // round up to unsigned char
            self::$ST |= ($data==0?REG_Z:0)|
                         ($data>>7?REG_N:0);

            MEM::write_mem8(self::$memaddr, $data);
        }


//        // accumulator shift
//        if((cpu.opcode&0xf)==0xa)
//        {
//            cpu.ps.b.c=cpu.a&0x80;
//
//            cpu.a<<=1;
//
//            cpu.ps.b.z=(!cpu.a);
//            cpu.ps.b.n=(cpu.a>>7)&1;
//        }
//        else
//        {
//            byte data = read_byte(cpu.memaddr);
//
//            cpu.ps.b.c=data&0x80;
//
//            data<<=1;
//            cpu.ps.b.z=(!data);
//            cpu.ps.b.n=(data>>7)&1;
//            write_byte(cpu.memaddr,data);
//        }
    }

    static function m6502_rol()
    {
        self::$ST &= ~(REG_Z|REG_N|REG_C);

        if((self::$opcode&0x0f)==0xa)
        {
            $tmp = self::$A;
            self::$A=((self::$A<<1)&0xfe)|(self::$ST&REG_C);
            self::$ST |= (self::$A==0?REG_Z:0)|
                         (self::$A>>7?REG_N:0)|
                         ($tmp>>7?REG_C:0);
        }
        else
        {
            $tmp = $data = MEM::read_mem8(self::$memaddr);

            $data=(($data<<1)&0xfe)|(self::$ST&REG_C);
            self::$ST |= ($data==0?REG_Z:0)|
                         ($data>>7?REG_N:0)|
                         ($tmp>>7?REG_C:0);

            MEM::write_mem8(self::$memaddr, $data);
        }

        self::$opbreak=TRUE;
//        byte tmp,value;
//
//        // accumulator shift
//        if((cpu.opcode&0xf)==0xa)
//            tmp = value = cpu.a;
//        else
//            tmp = value = read_byte(cpu.memaddr);
//
//        value=((value<<1)&0xfe)|cpu.ps.b.c;
//        cpu.ps.b.z=(!value);
//        cpu.ps.b.n=(value>>7)&1;
//        cpu.ps.b.c=(tmp>>7)&1;
//
//        if((cpu.opcode&0xf)==0xa)
//            cpu.a = value;
//        else
//            write_byte(cpu.memaddr,value);
    }

    static function m6502_ror()
    {
//        byte tmp,value;
//
//        // accumulator shift
//        if((cpu.opcode&0xf)==0xa)
//            tmp = value = cpu.a;
//        else
//            tmp = value = read_byte(cpu.memaddr);
//
//        value=((value>>1)&1)|cpu.ps.b.c;
//        cpu.ps.b.z=(!value);
//        cpu.ps.b.n=(value>>7)&1;
//        cpu.ps.b.c=tmp&1;
//
//        if((cpu.opcode&0xf)==0xa)
//            cpu.a = value;
//        else
//            write_byte(cpu.memaddr,value);
    }

    //
    // --- Arithmetic
    static function m6502_adc()
    {
//        byte data,cmp;
//
//        data = cmp = (signed char)read_byte(cpu.memaddr);
//        data+=cpu.a+cpu.ps.b.c;
//
//        cpu.ps.b.z=(!data);
//        cpu.ps.b.n=(data>>7)&1;
//        cpu.ps.b.c=(data>>7)&1;
//        cpu.ps.b.v=(!((cpu.a^cmp)&0x80) && ((cpu.a^data)&0x80));
//
//        cpu.a=data;
    }

    static function m6502_sbc()
    {
//        byte data,cmp;
//
//        cmp = (signed char)read_byte(cpu.memaddr);
//        data = cpu.a-cmp-(!cpu.ps.b.c);
//
//        cpu.ps.b.z=(!data);
//        cpu.ps.b.n=(data>>7)&1;
//        cpu.ps.b.c=!(data>>7)&1;
//        cpu.ps.b.v=(((cpu.a^cmp)&0x80) && ((cpu.a^data)&0x80));
//
//        cpu.a=data;
    }

    // --- Register Compares
    static function m6502_cmp()
    {
        $data = MEM::read_mem8(self::$memaddr);
        $cmp = (self::$A-$data)&0xff;   // rounding due to PHP integers

        self::$ST &= ~(REG_Z|REG_N|REG_C);
        self::$ST |= (self::$A>=$data?REG_C:0)|
                     (self::$A==$data?REG_Z:0)|
                     ($cmp>>7?REG_N:0);
    }

    static function m6502_cpx()
    {
        $data = MEM::read_mem8(self::$memaddr);
        $cmp = (self::$X-$data)&0xff;   // rounding due to PHP integers

        self::$ST &= ~(REG_Z|REG_N|REG_C);
        self::$ST |= (self::$X>=$data?REG_C:0)|
                     (self::$X==$data?REG_Z:0)|
                     ($cmp>>7?REG_N:0);
    }

    static function m6502_cpy()
    {
        $data = MEM::read_mem8(self::$memaddr);
        $cmp = (self::$Y-$data)&0xff;   // rounding due to PHP integers

        self::$ST &= ~(REG_Z|REG_N|REG_C);
        self::$ST |= (self::$Y>=$data?REG_C:0)|
                     (self::$Y==$data?REG_Z:0)|
                     ($cmp>>7?REG_N:0);
    }

    //
    // --- Register Transfers
    static function m6502_tay()
    {
        self::$Y = self::$A;
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$Y==0?REG_Z:0)|(self::$Y>>7?REG_N:0);
    }

    static function m6502_tya()
    {
        self::$A = self::$Y;
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$A==0?REG_Z:0)|(self::$A>>7?REG_N:0);
    }

    static function m6502_tax()
    {
        self::$X = self::$A;
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$X==0?REG_Z:0)|(self::$X>>7?REG_N:0);
    }

    static function m6502_txa()
    {
        self::$A = self::$X;
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$A==0?REG_Z:0)|(self::$A>>7?REG_N:0);
    }

    //
    // --- Logical
    static function m6502_and()
    {
        self::$A &= MEM::read_mem8(self::$memaddr);
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$A==0?REG_Z:0)|(self::$A>>7?REG_N:0);
    }

    static function m6502_eor()
    {
        self::$A ^= MEM::read_mem8(self::$memaddr);
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$A==0?REG_Z:0)|(self::$A>>7?REG_N:0);
    }

    static function m6502_ora()
    {
        self::$A |= MEM::read_mem8(self::$memaddr);
        self::$ST &= ~(REG_Z|REG_N);
        self::$ST |= (self::$A==0?REG_Z:0)|(self::$A>>7?REG_N:0);
    }

    static function m6502_bit()
    {
        $data = MEM::read_mem8(self::$memaddr);
        self::$ST &= ~(REG_Z|REG_N|REG_V);
        self::$ST |= ((self::$A&$data)==0?REG_Z:0)|
                      (self::$A&0x40?REG_V:0)|
                      (self::$A>>7?REG_N:0);
    }

    // system
    static function m6502_nop()
    {
        // do nothing
        return;
    }

    static function m6502_brk()
    {
        // do nothing
        return;
    }

    static function execute_irq()
    {
        self::$SP-=2;
        MEM::write_mem16(0x100+self::$SP, self::$PC);

        // read interrupt vector location
        self::$PC = MEM::read_mem16(0xfffa);
    }

    static function branch_jump()
    {
        $dis = MEM::read_mem8(self::$memaddr);

        if($dis>>7)
            self::$PC+=-(0x100-$dis); // force a signed value
        else
            self::$PC+=$dis;
    }
}
